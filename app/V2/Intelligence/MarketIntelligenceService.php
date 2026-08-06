<?php

declare(strict_types=1);

namespace LittyWatch\V2\Intelligence;

use PDO;
use RuntimeException;

final class MarketIntelligenceService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{markets:int,updated_at:string} */
    public function rebuild(): array
    {
        Schema::ensure($this->pdo);

        if (!$this->tableExists('structured_offers') || !$this->tableExists('messages')) {
            throw new RuntimeException('structured_offers of messages ontbreekt.');
        }

        $marketExpr = $this->columnExists('structured_offers', 'normalized_market_key')
            ? "COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key)"
            : 'so.market_key';

        $quality = $this->columnExists('structured_offers', 'quality_status')
            ? "AND COALESCE(so.quality_status, 'review') = 'accepted'"
            : '';
        $lifecycle = $this->columnExists('structured_offers', 'lifecycle_status')
            ? "AND COALESCE(so.lifecycle_status, 'active') = 'active'"
            : '';

        $sql = <<<SQL
SELECT
    {$marketExpr} AS market_key,
    MIN(so.item) AS item,
    so.trade_type,
    so.unit_price_ecto,
    m.player,
    m.posted_at
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
WHERE COALESCE({$marketExpr}, '') <> ''
  {$quality}
  {$lifecycle}
ORDER BY m.id DESC, so.id DESC
SQL;

        $rows = $this->pdo->query($sql)->fetchAll();
        $markets = [];

        foreach ($rows as $row) {
            $key = trim((string)($row['market_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $markets[$key] ??= [
                'item' => (string)($row['item'] ?? $key),
                'buy' => [],
                'sell' => [],
                'players' => [],
                'last_activity' => null,
            ];

            $player = trim((string)($row['player'] ?? ''));
            if ($player !== '') {
                $markets[$key]['players'][$player] = true;
            }
            $postedAt = (string)($row['posted_at'] ?? '');
            if ($postedAt !== '' && ($markets[$key]['last_activity'] === null || $postedAt > $markets[$key]['last_activity'])) {
                $markets[$key]['last_activity'] = $postedAt;
            }

            $price = (float)($row['unit_price_ecto'] ?? 0);
            if ($price <= 0) {
                continue;
            }
            $type = strtolower((string)($row['trade_type'] ?? ''));
            if ($type === 'buy') {
                $markets[$key]['buy'][] = $price;
            } elseif ($type === 'sell') {
                $markets[$key]['sell'][] = $price;
            }
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
                $buy = $market['buy'];
                $sell = $market['sell'];
                $bestWtb = $buy !== [] ? max($buy) : null;
                $bestWts = $sell !== [] ? min($sell) : null;
                $medianWtb = $this->median($buy);
                $medianWts = $this->median($sell);
                $spread = ($bestWtb !== null && $bestWts !== null) ? $bestWtb - $bestWts : null;
                $buyers = count($buy);
                $sellers = count($sell);
                $traders = count($market['players']);
                $total = $buyers + $sellers;

                $liquidity = min(100, (int)round(($traders * 10) + ($total * 2.5)));
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

        return ['markets' => count($markets), 'updated_at' => $now];
    }

    /** @return array<int,array<string,mixed>> */
    public function topDeals(int $limit = 25): array
    {
        Schema::ensure($this->pdo);
        $stmt = $this->pdo->prepare("SELECT * FROM market_intelligence WHERE best_wtb_ecto IS NOT NULL AND best_wts_ecto IS NOT NULL ORDER BY deal_score DESC, confidence_score DESC, liquidity_score DESC LIMIT :limit");
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function activeMarkets(int $limit = 100): array
    {
        Schema::ensure($this->pdo);
        $stmt = $this->pdo->prepare("SELECT * FROM market_intelligence ORDER BY last_activity DESC, unique_traders DESC LIMIT :limit");
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
        $n = count($values);
        $m = intdiv($n, 2);
        return $n % 2 === 1 ? $values[$m] : ($values[$m - 1] + $values[$m]) / 2;
    }

    /** @param array<int,float> $buy @param array<int,float> $sell */
    private function confidence(array $buy, array $sell, int $traders): int
    {
        $samples = count($buy) + count($sell);
        $base = min(70, $samples * 6 + $traders * 5);
        $dispersionPenalty = 0;
        foreach ([$buy, $sell] as $prices) {
            if (count($prices) >= 2) {
                $median = $this->median($prices) ?? 0;
                if ($median > 0) {
                    $range = max($prices) - min($prices);
                    $dispersionPenalty += min(20, (int)round(($range / $median) * 25));
                }
            }
        }
        if ($buy !== [] && $sell !== []) {
            $base += 20;
        }
        return max(0, min(100, $base - $dispersionPenalty));
    }

    private function dealScore(?float $bestWtb, ?float $bestWts, int $confidence, int $liquidity): int
    {
        if ($bestWtb === null || $bestWts === null || $bestWts <= 0) {
            return 0;
        }
        $marginPct = (($bestWtb - $bestWts) / $bestWts) * 100;
        $marginScore = max(0, min(100, (int)round($marginPct * 4)));
        return (int)round(($marginScore * 0.5) + ($confidence * 0.3) + ($liquidity * 0.2));
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
        $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
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
