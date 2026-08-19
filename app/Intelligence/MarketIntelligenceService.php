<?php

declare(strict_types=1);

namespace LittyWatch\Intelligence;

use PDO;
use RuntimeException;

final class MarketIntelligenceService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Rebuild market intelligence from accepted structured offers.
     *
     * Lifecycle flags are deliberately not trusted here. Earlier maintenance
     * versions could mark nearly every offer as superseded or expired. Instead,
     * this rebuild deduplicates independently: only the newest offer per
     * player + market + trade side is used.
     *
     * @return array<string,int|string>
     */
    public function rebuild(): array
    {
        Schema::ensure($this->pdo);

        if (!$this->tableExists('structured_offers') || !$this->tableExists('messages')) {
            throw new RuntimeException('structured_offers of messages ontbreekt.');
        }

        $marketExpr = $this->columnExists('structured_offers', 'normalized_market_key')
            ? "COALESCE(NULLIF(so.normalized_market_key, ''), NULLIF(so.market_key, ''), NULLIF(so.item_key, ''))"
            : "COALESCE(NULLIF(so.market_key, ''), NULLIF(so.item_key, ''))";

        $quality = $this->columnExists('structured_offers', 'quality_status')
            ? "AND COALESCE(so.quality_status, 'review') = 'accepted'"
            : '';

        $sql = <<<SQL
SELECT
    so.id,
    {$marketExpr} AS market_key,
    so.item,
    LOWER(COALESCE(so.trade_type, '')) AS trade_type,
    so.unit_price_ecto,
    COALESCE(m.player, '') AS player,
    m.posted_at,
    m.id AS message_id
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
WHERE COALESCE({$marketExpr}, '') <> ''
  {$quality}
ORDER BY m.id DESC, so.id DESC
SQL;

        $rows = $this->pdo->query($sql)->fetchAll();

        $markets = [];
        $seen = [];
        $acceptedRows = 0;
        $deduplicatedRows = 0;
        $pricedRows = 0;

        foreach ($rows as $row) {
            $acceptedRows++;

            $key = trim((string)($row['market_key'] ?? ''));
            $type = strtolower(trim((string)($row['trade_type'] ?? '')));
            if ($key === '' || !in_array($type, ['buy', 'sell'], true)) {
                continue;
            }

            $player = trim((string)($row['player'] ?? ''));
            $dedupePlayer = $player !== '' ? mb_strtolower($player) : 'message:' . (string)$row['message_id'];
            $dedupeKey = $key . '|' . $type . '|' . $dedupePlayer;

            // Rows are newest first, so the first row wins.
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $deduplicatedRows++;

            $markets[$key] ??= [
                'item' => trim((string)($row['item'] ?? '')) ?: $key,
                'buy' => [],
                'sell' => [],
                'players' => [],
                'last_activity' => null,
            ];

            if ($player !== '') {
                $markets[$key]['players'][mb_strtolower($player)] = true;
            }

            $postedAt = trim((string)($row['posted_at'] ?? ''));
            if (
                $postedAt !== ''
                && (
                    $markets[$key]['last_activity'] === null
                    || $this->activitySortValue($postedAt) > $this->activitySortValue((string)$markets[$key]['last_activity'])
                )
            ) {
                $markets[$key]['last_activity'] = $postedAt;
            }

            $price = (float)($row['unit_price_ecto'] ?? 0);
            if (!is_finite($price) || $price <= 0 || $price > 100000000) {
                continue;
            }

            $pricedRows++;
            $markets[$key][$type][] = $price;
        }

        $now = gmdate('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM market_intelligence');

            $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO market_intelligence (
    market_key, item, best_wtb_ecto, best_wts_ecto, median_wtb_ecto, median_wts_ecto,
    spread_ecto, buy_offers, sell_offers, unique_traders, liquidity_score, demand_score,
    confidence_score, deal_score, quality_label, liquidity_label, demand_label, last_activity, updated_at
) VALUES (
    :market_key, :item, :best_wtb, :best_wts, :median_wtb, :median_wts,
    :spread, :buy_offers, :sell_offers, :unique_traders, :liquidity_score, :demand_score,
    :confidence_score, :deal_score, :quality_label, :liquidity_label, :demand_label, :last_activity, :updated_at
)
SQL);

            foreach ($markets as $key => $market) {
                $buy = $this->trimOutliers($market['buy']);
                $sell = $this->trimOutliers($market['sell']);

                $bestWtb = $buy !== [] ? max($buy) : null;
                $bestWts = $sell !== [] ? min($sell) : null;
                $medianWtb = $this->median($buy);
                $medianWts = $this->median($sell);
                $spread = ($bestWtb !== null && $bestWts !== null) ? $bestWtb - $bestWts : null;

                $buyers = count($buy);
                $sellers = count($sell);
                $traders = count($market['players']);
                $total = $buyers + $sellers;

                $liquidity = min(100, (int)round(($traders * 9) + ($total * 3)));
                $demand = $total > 0 ? (int)round(100 * $buyers / $total) : 50;
                $confidence = $this->confidence($buy, $sell, $traders);
                $deal = $this->dealScore($bestWtb, $bestWts, $confidence, $liquidity);

                $insert->execute([
                    ':market_key' => $key,
                    ':item' => $market['item'],
                    ':best_wtb' => $bestWtb,
                    ':best_wts' => $bestWts,
                    ':median_wtb' => $medianWtb,
                    ':median_wts' => $medianWts,
                    ':spread' => $spread,
                    ':buy_offers' => $buyers,
                    ':sell_offers' => $sellers,
                    ':unique_traders' => $traders,
                    ':liquidity_score' => $liquidity,
                    ':demand_score' => $demand,
                    ':confidence_score' => $confidence,
                    ':deal_score' => $deal,
                    ':quality_label' => $this->qualityLabel($confidence),
                    ':liquidity_label' => $this->liquidityLabel($liquidity),
                    ':demand_label' => $this->demandLabel($demand),
                    ':last_activity' => $market['last_activity'],
                    ':updated_at' => $now,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $comparable = 0;
        foreach ($markets as $market) {
            if ($market['buy'] !== [] && $market['sell'] !== []) {
                $comparable++;
            }
        }

        return [
            'markets' => count($markets),
            'comparable_markets' => $comparable,
            'accepted_rows' => $acceptedRows,
            'latest_unique_rows' => $deduplicatedRows,
            'priced_rows' => $pricedRows,
            'updated_at' => $now,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function topDeals(int $limit = 25): array
    {
        Schema::ensure($this->pdo);
        $stmt = $this->pdo->prepare(
            "SELECT * FROM market_intelligence
             WHERE best_wtb_ecto IS NOT NULL
               AND best_wts_ecto IS NOT NULL
             ORDER BY deal_score DESC, confidence_score DESC, liquidity_score DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function activeMarkets(int $limit = 100): array
    {
        Schema::ensure($this->pdo);
        $stmt = $this->pdo->prepare(
            "SELECT * FROM market_intelligence
             ORDER BY last_activity DESC, unique_traders DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @param array<int,float> $values */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * Remove extreme values only when enough samples exist.
     *
     * @param array<int,float> $values
     * @return array<int,float>
     */
    private function trimOutliers(array $values): array
    {
        if (count($values) < 5) {
            return $values;
        }

        sort($values, SORT_NUMERIC);
        $q1 = $this->percentile($values, 0.25);
        $q3 = $this->percentile($values, 0.75);
        $iqr = $q3 - $q1;

        if ($iqr <= 0) {
            return $values;
        }

        $lower = max(0, $q1 - (1.5 * $iqr));
        $upper = $q3 + (1.5 * $iqr);
        $filtered = array_values(array_filter(
            $values,
            static fn(float $value): bool => $value >= $lower && $value <= $upper
        ));

        return $filtered !== [] ? $filtered : $values;
    }

    /** @param array<int,float> $sorted */
    private function percentile(array $sorted, float $percentile): float
    {
        $index = ($percentile * (count($sorted) - 1));
        $floor = (int)floor($index);
        $ceil = (int)ceil($index);

        if ($floor === $ceil) {
            return $sorted[$floor];
        }

        $weight = $index - $floor;
        return $sorted[$floor] * (1 - $weight) + $sorted[$ceil] * $weight;
    }

    /** @param array<int,float> $buy @param array<int,float> $sell */
    private function confidence(array $buy, array $sell, int $traders): int
    {
        $samples = count($buy) + count($sell);
        $base = min(72, ($samples * 6) + ($traders * 5));
        $dispersionPenalty = 0;

        foreach ([$buy, $sell] as $prices) {
            if (count($prices) < 2) {
                continue;
            }

            $median = $this->median($prices) ?? 0;
            if ($median <= 0) {
                continue;
            }

            $range = max($prices) - min($prices);
            $dispersionPenalty += min(22, (int)round(($range / $median) * 22));
        }

        if ($buy !== [] && $sell !== []) {
            $base += 18;
        }

        return max(0, min(100, $base - $dispersionPenalty));
    }

    private function dealScore(?float $bestWtb, ?float $bestWts, int $confidence, int $liquidity): int
    {
        if ($bestWtb === null || $bestWts === null || $bestWts <= 0 || $bestWtb <= $bestWts) {
            return 0;
        }

        $marginPercent = (($bestWtb - $bestWts) / $bestWts) * 100;
        $marginScore = max(0, min(100, (int)round($marginPercent * 4)));

        return (int)round(
            ($marginScore * 0.5)
            + ($confidence * 0.3)
            + ($liquidity * 0.2)
        );
    }

    private function activitySortValue(string $value): int
    {
        $timestamp = strtotime($value);
        return $timestamp !== false ? $timestamp : 0;
    }

    private function qualityLabel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Hoog',
            $score >= 55 => 'Redelijk',
            $score >= 30 => 'Laag',
            default => 'Onvoldoende data',
        };
    }

    private function liquidityLabel(int $score): string
    {
        return match (true) {
            $score >= 75 => 'Hoog',
            $score >= 45 => 'Gemiddeld',
            default => 'Laag',
        };
    }

    private function demandLabel(int $score): string
    {
        return match (true) {
            $score >= 70 => 'Veel vraag',
            $score <= 30 => 'Veel aanbod',
            default => 'Neutraal',
        };
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name"
        );
        $stmt->execute([':name' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        foreach ($this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }
}
