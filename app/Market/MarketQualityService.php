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

        $sql = "SELECT so.id,so.trade_type,so.item_key,so.normalized_market_key,so.price_amount,so.price_currency,so.price_ecto,so.unit_price_ecto,so.quantity,so.price_basis,so.raw_segment,so.quality_status,so.lifecycle_status,so.price_quality_reason,m.player
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
                 unit_price_ecto=CASE WHEN :clear_unit=1 THEN NULL ELSE COALESCE(:unit_price,unit_price_ecto) END,
                 price_basis=COALESCE(:price_basis,price_basis)
             WHERE id=:id"
        );

        $counts = ['trusted'=>0,'uncertain'=>0,'outlier'=>0,'unpriced'=>0,'groups'=>0];
        $candidates = [];

        foreach ($rows as $row) {
            $invalidateStaleUnit = $this->shouldInvalidateStaleCanonicalPrice($row);
            $recovery = $invalidateStaleUnit ? null : $this->recoverCanonicalPrice($row);
            $recoveredUnit = $recovery['unit'] ?? null;
            $recoveredBasis = $invalidateStaleUnit ? 'uncertain' : ($recovery['basis'] ?? null);
            if ($invalidateStaleUnit) {
                // Phase 3L.11: an explicit ambiguity decision must also remove a
                // stale unit price left by an earlier parser/reparse. Keeping it
                // through COALESCE would let the same bad price become an outlier.
                $row['unit_price_ecto'] = null;
                $row['price_basis'] = 'uncertain';
            } elseif ($recoveredUnit !== null) {
                // A recovery is intentionally limited to intrinsically explicit
                // offer-level syntax. Promote both fields together; otherwise a
                // recovered unit price would still be rejected solely because
                // the stale parser basis says "uncertain".
                $row['unit_price_ecto'] = $recoveredUnit;
                if ($recoveredBasis !== null) $row['price_basis'] = $recoveredBasis;
            }
            [$status, $reason] = $this->semanticStatus($row);
            $id = (int)$row['id'];
            $update->execute([
                ':status'=>$status,
                ':reason'=>$reason,
                ':score'=>null,
                ':baseline'=>null,
                ':unit_price'=>$recoveredUnit,
                ':price_basis'=>$recoveredBasis,
                ':clear_unit'=>$invalidateStaleUnit ? 1 : 0,
                ':id'=>$id
            ]);
            $counts[$status]++;
            if ($status === 'trusted' && in_array((string)$row['trade_type'], ['buy','sell'], true)) {
                $baselineKey=trim((string)($row['normalized_market_key']??''));
                if ($baselineKey==='') $baselineKey=(string)$row['item_key'];
                $candidates[$baselineKey][] = $row;
            }
        }

        foreach ($candidates as $baselineKey => $group) {
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
                    ':price_basis'=>null,
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

        $segment = trim((string)($row['raw_segment'] ?? ''));
        if ($segment !== '' && !$this->segmentHasMoney($segment) && ($amount !== null || ($unit !== null && $unit > 0))) {
            return ['uncertain', 'geldprijs_niet_aanwezig_in_offer_segment'];
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

    /**
     * @param array<string,mixed> $row
     * @return array{unit:float,basis:string}|null
     */
    private function recoverCanonicalPrice(array $row): ?array
    {
        $basis=strtolower(trim((string)($row['price_basis']??'')));
        $ecto=$this->ectoValue($row);
        if ($ecto===null || $ecto<=0) return null;

        $segment=trim((string)($row['raw_segment']??''));

        // Phase 3L.9: explicit quote syntax owns its own money amount. Never
        // reuse stale parser price_ecto when the segment contains alternatives.
        // "stacks 22e/ea" is a special Kamadan shorthand: /ea means each stack.
        if ($segment!=='' && preg_match('/\b(?:stack|stacks)\b[^\r\n,;|]{0,40}?(\d+(?:[.,]\d+)?)\s*(a|e|k|plat(?:inum)?)\s*(?:\/\s*)?(?:ea|each)\b/i',$segment,$m)) {
            $quoted=$this->moneyToEcto((float)str_replace(',','.',$m[1]),strtolower($m[2]));
            if ($quoted!==null && $quoted>0) return ['unit'=>$quoted/250.0,'basis'=>'stack'];
        }

        // When multiple explicit alternatives exist, the first quote in text is
        // the primary advertised price. Later values are bulk/alternative deals.
        $explicit=[];
        if ($segment!=='') {
            if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*(a|e|k|plat(?:inum)?)\s*(?:\/\s*|\bper\s+)(?:st|stk|stack)\b/i',$segment,$mm,PREG_SET_ORDER|PREG_OFFSET_CAPTURE)) {
                foreach($mm as $x) $explicit[]=['pos'=>$x[0][1],'amount'=>$x[1][0],'currency'=>$x[2][0],'basis'=>'stack'];
            }
            if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*(a|e|k|plat(?:inum)?)\s*(?:(?:[\/.\-]\s*)?(?:ea|each)\b|\bper\s+(?:unit|piece)\b)/i',$segment,$mm,PREG_SET_ORDER|PREG_OFFSET_CAPTURE)) {
                foreach($mm as $x) $explicit[]=['pos'=>$x[0][1],'amount'=>$x[1][0],'currency'=>$x[2][0],'basis'=>'each'];
            }
        }
        if ($explicit!==[]) {
            usort($explicit,static fn(array $a,array $b):int=>$a['pos']<=>$b['pos']);
            $x=$explicit[0];
            $quoted=$this->moneyToEcto((float)str_replace(',','.',(string)$x['amount']),strtolower((string)$x['currency']));
            if ($quoted!==null && $quoted>0) {
                if ($x['basis']==='stack') return ['unit'=>$quoted/250.0,'basis'=>'stack'];
                $itemKey=$this->canonicalCatalogKey((string)($row['item_key']??''));
                $catalogExplicit=$this->catalogSemantics((string)($row['item_key']??''));
                if ($catalogExplicit!==null && $catalogExplicit['basis']==='stack' && $catalogExplicit['size']>1 && !$this->isMixedBasisMarket($itemKey)) {
                    return ['unit'=>$quoted/$catalogExplicit['size'],'basis'=>'stack_inferred'];
                }
                return ['unit'=>$quoted,'basis'=>'each'];
            }
        }

        if ($segment!=='' && preg_match('/\b(?:stack|stacks)\b/i',$segment)) {
            preg_match_all('/(?<![a-z0-9.])(\d+(?:[.,]\d+)?)\s*(a|e|k|plat(?:inum)?)\b/i',$segment,$money,PREG_SET_ORDER);
            if (count($money)===1) {
                $quoted=$this->moneyToEcto((float)str_replace(',','.',$money[0][1]),strtolower($money[0][2]));
                if ($quoted!==null && $quoted>0) return ['unit'=>$quoted/250.0,'basis'=>'stack'];
            }
        }

        // For catalog-declared stack markets, `/ea` commonly means each
        // quoted stack/lot (Kamadan shorthand), not each underlying item.
        // Keep explicit numeric ratios (3=1e etc.) outside this shortcut.
        $catalog=$this->catalogSemantics((string)($row['item_key']??''));
        if ($segment!=='' && $catalog!==null && $catalog['basis']==='stack' && $catalog['size']>1
            && preg_match('/\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\s*\/\s*(?:ea|each)\b/i',$segment)
            && !preg_match('/(?<![a-z0-9.])\d+(?:[.,]\d+)?\s*(?::|=|\/)\s*\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\b/i',$segment)) {
            return ['unit'=>$ecto/$catalog['size'],'basis'=>'stack_inferred'];
        }

        // Phase 3L.10: live Kamadan conventions that cannot be represented by
        // one static catalog basis. Consets use bare ecto as per-set, while a
        // bare armbrace quote is the price of a full 250 conset stack.
        $canonicalKey=$this->canonicalCatalogKey((string)($row['item_key']??''));
        $currency=strtolower(trim((string)($row['price_currency']??'')));
        if ($canonicalKey==='conset' && $this->isSafeBareCatalogQuote($segment)) {
            if ($currency==='e') return ['unit'=>$ecto,'basis'=>'each_inferred'];
            if ($currency==='a') return ['unit'=>$ecto/250.0,'basis'=>'stack_inferred'];
        }

        // Mixed-basis markets must never inherit a parser/catalog basis from a
        // single bare money quote. Explicit /ea, /stack and stack wording were
        // handled above; without those signals the quote remains unresolved.
        if ($this->isMixedBasisMarket($canonicalKey) && $this->isSafeBareCatalogQuote($segment)) {
            return null;
        }

        // First trust parser-owned canonical bases after offer-level stack
        // wording had a chance to correct an `each` interpretation.
        if (in_array($basis,['each','each_inferred'],true)) {
            return ['unit'=>$ecto,'basis'=>$basis];
        }
        if (in_array($basis,['stack','stack_inferred','stack_total','total','ratio','exchange','set'],true)) {
            $quantity=isset($row['quantity']) && $row['quantity']!==null ? (float)$row['quantity'] : null;
            if (($quantity===null || $quantity<=0) && in_array($basis,['stack','stack_inferred'],true)) $quantity=250.0;
            if ($quantity!==null && $quantity>0) return ['unit'=>$ecto/$quantity,'basis'=>$basis];
        }

        // Phase 3L.5 safety net: recover only explicit syntax from the final
        // offer slice. The returned basis is part of the recovery contract, so
        // Market Quality cannot reject a safe recovered unit merely because an
        // earlier full-message parse left price_basis='uncertain'.
        if ($segment==='') return null;

        // N:price / N=price / N/price = total price for N items.
        if (preg_match('/(?<![a-z0-9.])(\d+(?:[.,]\d+)?)\s*(?::|=|\/)\s*\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\b/i',$segment,$m)) {
            $quantity=(float)str_replace(',','.',$m[1]);
            return $quantity>0 ? ['unit'=>$ecto/$quantity,'basis'=>'ratio'] : null;
        }

        // Explicit stack quote: price/stk, price/stack, price per stack.
        if (preg_match('/\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\s*(?:\/|\-|\bper\s+)(?:st|stk|stack)\b/i',$segment)) {
            return ['unit'=>$ecto/250.0,'basis'=>'stack'];
        }

        // Explicit each quote: price/ea, price.each, price each, price per unit.
        if (preg_match('/\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\s*(?:(?:[\/.\-]\s*)?(?:ea|each)\b|\bper\s+(?:unit|piece)\b)/i',$segment)) {
            return ['unit'=>$ecto,'basis'=>'each'];
        }

        // "27e x4" is four available at 27e each.
        if (preg_match('/\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\s*x\s*\d+\b/i',$segment)) {
            return ['unit'=>$ecto,'basis'=>'each'];
        }

        // Phase 3L.6: use catalog-owned quote semantics for a single bare money
        // quote. This mirrors ParserEngine's canonicalization, but runs on the
        // final accepted offer slice so multi-offer context cannot erase safe
        // per-item/per-stack knowledge. Ambiguous lists, ranges and multi-price
        // segments are deliberately excluded before catalog inference.
        if (!$this->isSafeBareCatalogQuote($segment)) return null;
        $bareKey=$this->canonicalCatalogKey((string)($row['item_key']??''));
        // These markets are commonly quoted both per item and per stack in live
        // Kamadan data. A bare number is therefore not enough to infer basis.
        if ($this->isMixedBasisMarket($bareKey)) return null;
        $semantics=$this->catalogSemantics((string)($row['item_key']??''));
        if ($semantics===null) return null;
        if ($semantics['basis']==='stack' && $semantics['size']>1) {
            return ['unit'=>$ecto/$semantics['size'],'basis'=>'stack_inferred'];
        }
        if ($semantics['basis']==='each') {
            return ['unit'=>$ecto,'basis'=>'each_inferred'];
        }

        return null;
    }


    private function isSafeBareCatalogQuote(string $segment): bool
    {
        // Exactly one money token. Multiple amounts usually mean a range,
        // alternative prices, a package split or another item's price.
        preg_match_all('/(?<![a-z0-9.])\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\b/i',$segment,$money);
        if (count($money[0]??[])!==1) return false;

        // Numeric ranges (225-675e) and shared item lists remain uncertain.
        if (preg_match('/\d+(?:[.,]\d+)?\s*[-–—]\s*\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\b/i',$segment)) return false;
        if (preg_match('/\s\/\s/', $segment)) return false;

        // Package / bundle wording is never an implicit each/stack quote.
        if (preg_match('/\b(?:bundle|package|pack|set of|all for|together)\b/i',$segment)) return false;
        return true;
    }

    /** @return array{basis:string,size:float}|null */
    private function catalogSemantics(string $itemKey): ?array
    {
        if ($itemKey==='') return null;
        static $map=null;
        if ($map===null) {
            $map=[];
            $path=dirname(__DIR__).'/Data/items.json';
            $decoded=is_file($path) ? json_decode((string)file_get_contents($path),true) : null;
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_array($item) || empty($item['key'])) continue;
                    $basis=strtolower(trim((string)($item['market_quote_basis']??'')));
                    $category=strtolower(trim((string)($item['category']??'')));
                    $size=(float)($item['market_quote_size']??$item['market_stack_size']??0);
                    $catalogKey=$this->canonicalCatalogKey((string)$item['key']);
                    if ($catalogKey==='') continue;
                    if ($basis==='stack') {
                        if ($size<=1) $size=250.0;
                        $map[$catalogKey]=['basis'=>'stack','size'=>$size];
                    } elseif ($basis==='each') {
                        $map[$catalogKey]=['basis'=>'each','size'=>1.0];
                    } elseif (!in_array($category,['currency','material','consumable'],true)) {
                        // Same conservative default as ParserEngine: concrete
                        // non-commodity catalog items are quoted per item.
                        $map[$catalogKey]=['basis'=>'each','size'=>1.0];
                    }
                }
            }
        }
        return $map[$this->canonicalCatalogKey($itemKey)]??null;
    }

    private function canonicalCatalogKey(string $value): string
    {
        // StructuredOfferWriter uses underscore keys while the static catalog
        // historically uses hyphens. Compare both through one representation.
        return trim((string)preg_replace('/[^a-z0-9]+/', '_', strtolower($value)), '_');
    }

    /** @param array<string,mixed> $row */
    private function recoverCanonicalUnit(array $row): ?float
    {
        // Kept as a tiny compatibility wrapper for existing regression probes.
        $recovery=$this->recoverCanonicalPrice($row);
        return $recovery['unit'] ?? null;
    }

    private function moneyToEcto(float $amount,string $currency): ?float
    {
        if ($amount<=0) return null;
        return match($currency){
            'a'=>$amount*27.0,
            'e'=>$amount,
            'k','plat','platinum'=>$amount/15.0,
            default=>null,
        };
    }

    /** @param array<string,mixed> $row */
    private function ectoValue(array $row): ?float
    {
        $ecto=isset($row['price_ecto']) && $row['price_ecto']!==null ? (float)$row['price_ecto'] : null;
        if ($ecto!==null && $ecto>0) return $ecto;
        $amount=isset($row['price_amount']) && $row['price_amount']!==null ? (float)$row['price_amount'] : null;
        $currency=strtolower(trim((string)($row['price_currency']??'')));
        if ($amount===null || $amount<=0) return null;
        return match($currency){
            'a'=>$amount*27.0,
            'e'=>$amount,
            'k'=>$amount/15.0,
            default=>null,
        };
    }

    /** @param array<string,mixed> $row */
    private function shouldInvalidateStaleCanonicalPrice(array $row): bool
    {
        if ((string)($row['price_quality_reason'] ?? '') === 'handmatig_goedgekeurd') return false;
        $key=$this->canonicalCatalogKey((string)($row['item_key']??''));
        if (!$this->isMixedBasisMarket($key)) return false;
        $segment=trim((string)($row['raw_segment']??''));
        if ($segment==='') return false;

        preg_match_all('/(?<![a-z0-9.])\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\b/i',$segment,$money);
        if (count($money[0]??[])!==1) return false;

        // Explicit basis signals are safe and are handled by recoveryCanonicalPrice.
        if (preg_match('/\b(?:stack|stacks)\b/i',$segment)) return false;
        if (preg_match('/\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\s*(?:(?:[\/.\-]\s*)?(?:ea|each)\b|(?:\/|\-|\bper\s+)(?:st|stk|stack)\b|\bper\s+(?:unit|piece)\b)/i',$segment)) return false;
        if (preg_match('/(?<![a-z0-9.])\d+(?:[.,]\d+)?\s*(?::|=|\/)\s*\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\b/i',$segment)) return false;

        // A trailing slash, tilde or a plain bare quote still has no basis.
        return true;
    }

    private function isMixedBasisMarket(string $canonicalKey): bool
    {
        return in_array($canonicalKey,['gift_of_the_traveler','tengu_support_flare'],true);
    }

    private function segmentHasMoney(string $segment): bool
    {
        return (bool)preg_match('/(?<![a-z0-9.])\d+(?:[.,]\d+)?\s*(?:a|e|k|plat(?:inum)?)\b/i',$segment);
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
