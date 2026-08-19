<?php

declare(strict_types=1);

namespace LittyWatch\Snapshots;

use PDO;

final class MarketStats
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function activeMarkets(int $limit = 100): array
    {
        $sql = <<<SQL
SELECT
    COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key) AS market_key,
    MIN(so.item) AS item,
    COUNT(*) AS active_offers,
    COUNT(DISTINCT m.player) AS unique_traders,
    MAX(m.posted_at) AS last_activity
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
WHERE COALESCE(so.lifecycle_status, 'active') = 'active'
  AND COALESCE(so.quality_status, 'accepted') = 'accepted'
GROUP BY COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key)
ORDER BY active_offers DESC, last_activity DESC
LIMIT :limit
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function summarize(string $marketKey): ?array
    {
        $rows = $this->priceRows($marketKey);
        if ($rows === []) {
            return null;
        }

        $buy = [];
        $sell = [];
        $players = [];
        $item = null;

        foreach ($rows as $row) {
            $item ??= $row['item'] ?? $marketKey;
            if (($row['player'] ?? '') !== '') {
                $players[(string)$row['player']] = true;
            }
            $price = isset($row['unit_price_ecto']) ? (float)$row['unit_price_ecto'] : 0.0;
            if ($price <= 0) {
                continue;
            }
            if (($row['trade_type'] ?? '') === 'buy') {
                $buy[] = $price;
            } elseif (($row['trade_type'] ?? '') === 'sell') {
                $sell[] = $price;
            }
        }

        return [
            'market_key' => $marketKey,
            'item' => $item,
            'best_wtb_ecto' => $buy !== [] ? max($buy) : null,
            'best_wts_ecto' => $sell !== [] ? min($sell) : null,
            'median_wtb_ecto' => $this->median($buy),
            'median_wts_ecto' => $this->median($sell),
            'active_offers' => count($rows),
            'unique_traders' => count($players),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function priceRows(string $marketKey): array
    {
        $sql = <<<SQL
SELECT
    so.item,
    so.trade_type,
    so.unit_price_ecto,
    m.player
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
WHERE COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key) = :market_key
  AND COALESCE(so.lifecycle_status, 'active') = 'active'
  AND COALESCE(so.quality_status, 'accepted') = 'accepted'
  AND so.unit_price_ecto IS NOT NULL
  AND so.unit_price_ecto > 0
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':market_key' => $marketKey]);
        return $stmt->fetchAll();
    }

    /** @param array<int, float> $values */
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
}
