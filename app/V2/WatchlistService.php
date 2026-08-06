<?php

declare(strict_types=1);

namespace LittyWatch\V2;

use PDO;

final class WatchlistService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $sql = <<<SQL
SELECT
    w.id,
    w.market_key,
    COALESCE(NULLIF(w.label, ''), MIN(so.item), w.market_key) AS label,
    w.created_at,
    COUNT(CASE WHEN so.trade_type = 'buy' THEN 1 END) AS buy_offers,
    COUNT(CASE WHEN so.trade_type = 'sell' THEN 1 END) AS sell_offers,
    MAX(CASE WHEN so.trade_type = 'buy' THEN so.unit_price_ecto END) AS best_wtb_ecto,
    MIN(CASE WHEN so.trade_type = 'sell' THEN so.unit_price_ecto END) AS best_wts_ecto,
    MAX(m.posted_at) AS last_activity
FROM watchlist w
LEFT JOIN structured_offers so
  ON COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key) = w.market_key
 AND COALESCE(so.lifecycle_status, 'active') = 'active'
 AND COALESCE(so.quality_status, 'accepted') = 'accepted'
LEFT JOIN messages m ON m.id = so.message_id
GROUP BY w.id, w.market_key, w.label, w.created_at
ORDER BY last_activity DESC, w.created_at DESC
SQL;
        return $this->pdo->query($sql)->fetchAll();
    }

    public function add(string $marketKey, ?string $label = null): void
    {
        $marketKey = trim($marketKey);
        if ($marketKey === '') {
            throw new \InvalidArgumentException('market_key ontbreekt.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO watchlist (market_key, label) VALUES (:key, :label) ON CONFLICT(market_key) DO UPDATE SET label = COALESCE(excluded.label, watchlist.label)');
        $stmt->execute([':key' => $marketKey, ':label' => $label !== null ? trim($label) : null]);
    }

    public function remove(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM watchlist WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
