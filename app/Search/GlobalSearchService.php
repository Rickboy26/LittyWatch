<?php

declare(strict_types=1);

namespace LittyWatch\Search;

use PDO;

final class GlobalSearchService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    public function search(string $query, int $limit = 12): array
    {
        $query = trim($query);
        if ($query === '') {
            return [
                'markets' => [],
                'items' => [],
                'traders' => [],
                'offers' => [],
            ];
        }

        return [
            'markets' => $this->markets($query, $limit),
            'items' => $this->items($query, $limit),
            'traders' => $this->traders($query, $limit),
            'offers' => $this->offers($query, $limit),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function markets(string $query, int $limit): array
    {
        if ($this->tableExists('market_intelligence')) {
            $stmt = $this->pdo->prepare(<<<'SQL'
SELECT
    market_key,
    item,
    best_wtb_ecto,
    best_wts_ecto,
    median_wtb_ecto,
    median_wts_ecto,
    liquidity_label,
    confidence_score,
    deal_score,
    last_activity
FROM market_intelligence
WHERE item LIKE :query
   OR market_key LIKE :query
ORDER BY
    CASE WHEN LOWER(item) = LOWER(:exact) THEN 0 ELSE 1 END,
    deal_score DESC,
    confidence_score DESC,
    last_activity DESC
LIMIT :limit
SQL);
            $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
            $stmt->bindValue(':exact', $query, PDO::PARAM_STR);
            $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        if (!$this->tableExists('structured_offers')) {
            return [];
        }

        $marketExpr = $this->columnExists('structured_offers', 'normalized_market_key')
            ? "COALESCE(NULLIF(normalized_market_key, ''), market_key)"
            : 'market_key';

        $stmt = $this->pdo->prepare(<<<SQL
SELECT
    {$marketExpr} AS market_key,
    MIN(item) AS item,
    NULL AS best_wtb_ecto,
    NULL AS best_wts_ecto,
    NULL AS median_wtb_ecto,
    NULL AS median_wts_ecto,
    NULL AS liquidity_label,
    NULL AS confidence_score,
    NULL AS deal_score,
    MAX(parsed_at) AS last_activity
FROM structured_offers
WHERE item LIKE :query
   OR {$marketExpr} LIKE :query
GROUP BY {$marketExpr}
ORDER BY COUNT(*) DESC
LIMIT :limit
SQL);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function items(string $query, int $limit): array
    {
        if (!$this->tableExists('structured_offers')) {
            return [];
        }

        $quality = $this->columnExists('structured_offers', 'quality_status')
            ? "AND COALESCE(quality_status, 'review') = 'accepted'"
            : '';

        $stmt = $this->pdo->prepare(<<<SQL
SELECT
    item,
    MIN(item_key) AS item_key,
    COUNT(*) AS offers_count,
    COUNT(DISTINCT market_key) AS market_count,
    MAX(parsed_at) AS last_activity
FROM structured_offers
WHERE item LIKE :query
  {$quality}
GROUP BY item
ORDER BY
    CASE WHEN LOWER(item) = LOWER(:exact) THEN 0 ELSE 1 END,
    offers_count DESC,
    item COLLATE NOCASE ASC
LIMIT :limit
SQL);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':exact', $query, PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function traders(string $query, int $limit): array
    {
        if (!$this->tableExists('messages')) {
            return [];
        }

        $stmt = $this->pdo->prepare(<<<'SQL'
SELECT
    player,
    COUNT(*) AS messages_count,
    MIN(posted_at) AS first_seen,
    MAX(posted_at) AS last_seen
FROM messages
WHERE TRIM(COALESCE(player, '')) <> ''
  AND player LIKE :query
GROUP BY player
ORDER BY
    CASE WHEN LOWER(player) = LOWER(:exact) THEN 0 ELSE 1 END,
    last_seen DESC,
    messages_count DESC
LIMIT :limit
SQL);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':exact', $query, PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function offers(string $query, int $limit): array
    {
        if (!$this->tableExists('structured_offers') || !$this->tableExists('messages')) {
            return [];
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

        $stmt = $this->pdo->prepare(<<<SQL
SELECT
    so.id,
    so.trade_type,
    so.item,
    {$marketExpr} AS market_key,
    so.unit_price_ecto,
    so.raw_segment,
    so.confidence,
    m.player,
    m.posted_at
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
WHERE (
       so.item LIKE :query
    OR so.raw_segment LIKE :query
    OR m.player LIKE :query
    OR {$marketExpr} LIKE :query
)
{$quality}
{$lifecycle}
ORDER BY m.id DESC, so.id DESC
LIMIT :limit
SQL);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        return [
            'markets' => $this->countTable('market_intelligence'),
            'offers' => $this->countTable('structured_offers'),
            'traders' => $this->distinctCount('messages', 'player'),
            'alerts' => $this->countWhere('alerts', "is_enabled = 1"),
            'watchlist' => $this->countTable('watchlist'),
            'snapshots' => $this->countTable('market_snapshots'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function hotDeals(int $limit = 8): array
    {
        if (!$this->tableExists('market_intelligence')) {
            return [];
        }

        $stmt = $this->pdo->prepare(<<<'SQL'
SELECT *
FROM market_intelligence
WHERE best_wtb_ecto > 0
  AND best_wts_ecto > 0
ORDER BY deal_score DESC, confidence_score DESC, last_activity DESC
LIMIT :limit
SQL);
        $stmt->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function recentAlertEvents(int $limit = 8): array
    {
        if (!$this->tableExists('alert_events')) {
            return [];
        }

        $stmt = $this->pdo->prepare(<<<'SQL'
SELECT e.*, a.label
FROM alert_events e
LEFT JOIN alerts a ON a.id = e.alert_id
ORDER BY e.id DESC
LIMIT :limit
SQL);
        $stmt->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function watchlistMarkets(int $limit = 8): array
    {
        if (!$this->tableExists('watchlist')) {
            return [];
        }

        $join = $this->tableExists('market_intelligence')
            ? 'LEFT JOIN market_intelligence mi ON mi.market_key = w.market_key'
            : '';

        $select = $this->tableExists('market_intelligence')
            ? ', mi.item, mi.best_wtb_ecto, mi.best_wts_ecto, mi.deal_score, mi.confidence_score, mi.last_activity'
            : ", NULL AS item, NULL AS best_wtb_ecto, NULL AS best_wts_ecto, NULL AS deal_score, NULL AS confidence_score, NULL AS last_activity";

        $stmt = $this->pdo->prepare(<<<SQL
SELECT w.id, w.market_key, w.label, w.created_at
{$select}
FROM watchlist w
{$join}
ORDER BY w.id DESC
LIMIT :limit
SQL);
        $stmt->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function countTable(string $table): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        return (int)$this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function distinctCount(string $table, string $column): int
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return 0;
        }
        return (int)$this->pdo->query(
            "SELECT COUNT(DISTINCT NULLIF(TRIM({$column}), '')) FROM {$table}"
        )->fetchColumn();
    }

    private function countWhere(string $table, string $where): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        return (int)$this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
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
