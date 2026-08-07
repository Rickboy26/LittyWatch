<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use PDO;

/**
 * Phase 3I: independent market-price quality layer.
 * Parser correctness and price trust are deliberately separate concerns.
 */
final class MarketQualityService
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array{trusted:int,uncertain:int,outlier:int,unpriced:int,groups:int} */
    public function rebuildAll(): array
    {
        return $this->rebuildForItemKeys([]);
    }

    /** @param list<string> $itemKeys @return array{trusted:int,uncertain:int,outlier:int,unpriced:int,groups:int} */
    public function rebuildForItemKeys(array $itemKeys): array
    {
        $filter = '';
        $params = [];
        $keys = array_values(array_unique(array_filter(array_map('strval', $itemKeys), static fn(string $v): bool => $v !== '')));
        if ($keys !== []) {
            $marks = [];
            foreach ($keys as $i => $key) {
                $marks[] = ':k'.$i;
                $params[':k'.$i] = $key;
            }
            $filter = ' AND so.item_key IN ('.implode(',', $marks).')';
        }

        $sql = "SELECT so.id,so.trade_type,so.item_key,so.price_amount,so.price_currency,so.price_ecto,so.unit_price_ecto,so.quantity,so.price_basis,so.quality_status,so.lifecycle_status,so.price_quality_reason,m.player
                FROM structured_offers so
                JOIN messages m ON m.id=so.message_id
                WHERE so.quality_status='accepted'
                  AND COALESCE(so.lifecycle_status,'active')='active'{$filter}
                ORDER BY so.item_key,so.id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $update = $this->pdo->prepare(
            "UPDATE structured_offers
             SET price_quality_status=:status,
                 price_quality_reason=:reason,
                 price_outlier_score=:score,
                 price_baseline_ecto=:baseline,
                 unit_price_ecto=COALESCE(:unit_price,unit_price_ecto)
             WHERE id=:id"
        );

        $counts = ['trusted'=>0,'uncertain'=>0,'outlier'=>0,'unpriced'=>0,'groups'=>0];
        $candidates = [];

        foreach ($rows as $row) {
            $recoveredUnit = $this->recoverCanonicalUnit($row);
            if (($row['unit_price_ecto'] === null || (float)$row['unit_price_ecto'] <= 0) && $recoveredUnit !== null) {
                $row['unit_price_ecto'] = $recoveredUnit;
            }
            [$status, $reason] = $this->semanticStatus($row);
            $id = (int)$row['id'];
            $update->execute([
                ':status'=>$status,
                ':reason'=>$reason,
                ':score'=>null,
                ':baseline'=>null,
                ':unit_price'=>$recoveredUnit,
                ':id'=>$id
            ]);
            $counts[$status]++;
            if ($status === 'trusted' && in_array((string)$row['trade_type'], ['buy','sell'], true)) {
                $candidates[(string)$row['item_key']][] = $row;
            }
        }

        foreach ($candidates as $itemKey => $group) {
            $prices = [];
            $traders = [];
            foreach ($group as $row) {
                $price = (float)$row['unit_price_ecto'];
                if ($price <= 0) continue;
                $prices[] = $price;
                $traders[mb_strtolower(trim((string)$row['player']))] = true;
            }
            if (count($prices) < 5 || count($traders) < 3) continue;

            $median = $this->median($prices);
            if ($median === null || $median <= 0) continue;
            $deviations = array_map(static fn(float $v): float => abs($v - $median), $prices);
            $mad = $this->median($deviations) ?? 0.0;
            $counts['groups']++;

            foreach ($group as $row) {
                if ((string)($row['price_quality_reason'] ?? '') === 'handmatig_goedgekeurd') continue;
                $price = (float)$row['unit_price_ecto'];
                if ($price <= 0) continue;
                $ratio = $price / $median;
                $robustZ = $mad > 0.000001 ? abs($price - $median) / (1.4826 * $mad) : 0.0;

                // Require a very large relative departure. This prevents normal
                // spreads in illiquid GW1 markets from being treated as errors.
                $extremeRatio = $ratio >= 4.0 || $ratio <= 0.25;
                $extremeZ = $mad > 0.000001 && $robustZ >= 8.0 && ($ratio >= 2.5 || $ratio <= 0.4);
                if (!$extremeRatio && !$extremeZ) continue;

                $score = $mad > 0.000001 ? $robustZ : max($ratio, 1 / max($ratio, 0.000001));
                $reason = sprintf('market_outlier: %.3fe vs mediaan %.3fe (%dx verschil)', $price, $median, (int)round(max($ratio, 1 / max($ratio, 0.000001))));
                $update->execute([
                    ':status'=>'outlier',
                    ':reason'=>$reason,
                    ':score'=>round($score, 3),
                    ':baseline'=>$median,
                    ':unit_price'=>null,
                    ':id'=>(int)$row['id'],
                ]);
                $counts['trusted']--;
                $counts['outlier']++;
            }
        }

        return $counts;
    }

    /** @param array<string,mixed> $row @return array{0:string,1:string} */
    private function semanticStatus(array $row): array
    {
        $tradeType = (string)($row['trade_type'] ?? '');
        if (!in_array($tradeType, ['buy','sell'], true)) return ['unpriced', 'trade_offer'];

        $amount = isset($row['price_amount']) && $row['price_amount'] !== null ? (float)$row['price_amount'] : null;
        $unit = isset($row['unit_price_ecto']) && $row['unit_price_ecto'] !== null ? (float)$row['unit_price_ecto'] : null;
        $currency = strtolower(trim((string)($row['price_currency'] ?? '')));
        $basis = strtolower(trim((string)($row['price_basis'] ?? '')));
        $itemKey = (string)($row['item_key'] ?? '');
        if ((string)($row['price_quality_reason'] ?? '') === 'handmatig_goedgekeurd') {
            return ['trusted', 'handmatig_goedgekeurd'];
        }

        if ($amount === null && ($unit === null || $unit <= 0)) return ['unpriced', 'geen_geldprijs'];
        if ($unit === null || $unit <= 0) return ['uncertain', 'geldprijs_gevonden_maar_geen_betrouwbare_unitprijs'];
        if (!in_array($currency, ['a','e','k'], true)) return ['uncertain', 'onbekende_prijseenheid'];
        if (in_array($basis, ['bundle','currency_exchange','unknown','currency_conversion','unqualified','uncertain','range'], true)) {
            return ['uncertain', 'onzekere_prijsbasis: '.($basis !== '' ? $basis : 'unknown')];
        }

        // Preserve the conservative Armbrace rule from 3E, but expose it through
        // the generic price-quality layer instead of hiding it only in SQL/views.
        if ($itemKey === 'armbrace-of-truth') {
            if ($currency !== 'e' || $amount === null || $amount <= 0 || $amount > 100 || abs($unit - $amount) >= 0.001) {
                return ['uncertain', 'armbrace_unitprijs_niet_explicitiet_betrouwbaar'];
            }
        }

        return ['trusted', 'semantiek_ok'];
    }

    /** @param array<string,mixed> $row */
    private function recoverCanonicalUnit(array $row): ?float
    {
        $basis=strtolower(trim((string)($row['price_basis']??'')));
        if (!in_array($basis,['each','each_inferred','stack','stack_inferred','stack_total','total','ratio','exchange','set'],true)) {
            return null;
        }

        $ecto=isset($row['price_ecto']) && $row['price_ecto']!==null ? (float)$row['price_ecto'] : null;
        if ($ecto===null || $ecto<=0) {
            $amount=isset($row['price_amount']) && $row['price_amount']!==null ? (float)$row['price_amount'] : null;
            $currency=strtolower(trim((string)($row['price_currency']??'')));
            if ($amount===null || $amount<=0) return null;
            $ecto=match($currency){
                'a'=>$amount*27.0,
                'e'=>$amount,
                'k'=>$amount/15.0,
                default=>null,
            };
        }
        if ($ecto===null || $ecto<=0) return null;

        if (in_array($basis,['each','each_inferred'],true)) return $ecto;
        $quantity=isset($row['quantity']) && $row['quantity']!==null ? (float)$row['quantity'] : null;
        if ($quantity===null || $quantity<=0) {
            if (in_array($basis,['stack','stack_inferred'],true)) $quantity=250.0;
            else return null;
        }
        return $ecto/$quantity;
    }

    /** @param list<float> $values */
    private function median(array $values): ?float
    {
        if ($values === []) return null;
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        return $count % 2 === 1
            ? (float)$values[$middle]
            : (((float)$values[$middle - 1] + (float)$values[$middle]) / 2);
    }
}
