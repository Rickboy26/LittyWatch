<?php
declare(strict_types=1);

namespace LittyWatch\V2\Repositories;

use LittyWatch\V2\Core\Database;
use PDO;

final class DashboardRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function stats(): array
    {
        return [
            'messages' => $this->countTable('messages'),
            'offers' => $this->countTable('offers'),
            'structured' => $this->countTable('structured_offers'),
            'markets' => $this->countDistinctMarketKeys(),
            'watchlist' => $this->countTable('watchlist'),
        ];
    }

    public function topMarkets(int $limit): array
    {
        if (!$this->tableExists('structured_offers')) {
            return [];
        }

        $marketExpr = $this->columnExists('structured_offers', 'normalized_market_key')
            ? "COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key)"
            : 'so.market_key';

        $qualityClause = $this->columnExists('structured_offers', 'quality_status')
            ? "AND COALESCE(so.quality_status, 'review') = 'accepted'"
            : '';

        $lifecycleClause = $this->columnExists('structured_offers', 'lifecycle_status')
            ? "AND COALESCE(so.lifecycle_status, 'active') = 'active'"
            : '';

        $sql = "SELECT {$marketExpr} AS market_key,
                       MIN(so.item) AS item,
                       COUNT(*) AS offers,
                       COUNT(DISTINCT m.player) AS traders,
                       MAX(COALESCE(m.posted_at, '')) AS latest
                FROM structured_offers so
                JOIN messages m ON m.id = so.message_id
                WHERE COALESCE({$marketExpr}, '') <> ''
                  {$qualityClause}
                  {$lifecycleClause}
                GROUP BY {$marketExpr}
                ORDER BY offers DESC, latest DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function latestOffers(int $limit): array
    {
        $limit = max(1, min(100, $limit));

        if ($this->tableExists('structured_offers')) {
            $attributeExpr = $this->columnExists('structured_offers', 'attribute_name')
                ? 'so.attribute_name'
                : ($this->columnExists('structured_offers', 'attribute') ? 'so.attribute' : 'NULL');

            $qualityClause = $this->columnExists('structured_offers', 'quality_status')
                ? "WHERE COALESCE(so.quality_status, 'review') = 'accepted'"
                : '';
            $lifecycleClause = $this->columnExists('structured_offers', 'lifecycle_status')
                ? (($qualityClause === '' ? 'WHERE' : 'AND') . " COALESCE(so.lifecycle_status, 'active') = 'active'")
                : '';

            $sql = "SELECT so.trade_type,
                           so.item,
                           so.requirement,
                           {$attributeExpr} AS attribute,
                           so.price_amount,
                           so.price_currency,
                           m.player,
                           COALESCE(m.posted_at, '') AS observed_at
                    FROM structured_offers so
                    JOIN messages m ON m.id = so.message_id
                    {$qualityClause}
                    {$lifecycleClause}
                    ORDER BY m.id DESC, so.id DESC
                    LIMIT :limit";
        } elseif ($this->tableExists('offers')) {
            $sql = "SELECT o.trade_type,
                           o.item,
                           NULL AS requirement,
                           NULL AS attribute,
                           o.price_amount,
                           o.price_currency,
                           m.player,
                           COALESCE(m.posted_at, '') AS observed_at
                    FROM offers o
                    JOIN messages m ON m.id = o.message_id
                    ORDER BY m.id DESC, o.id DESC
                    LIMIT :limit";
        } else {
            return [];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function countTable(string $table): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    private function countDistinctMarketKeys(): int
    {
        if (!$this->tableExists('structured_offers')) {
            return 0;
        }

        $expr = $this->columnExists('structured_offers', 'normalized_market_key')
            ? "COALESCE(NULLIF(normalized_market_key, ''), market_key)"
            : 'market_key';

        return (int) $this->pdo
            ->query("SELECT COUNT(DISTINCT {$expr}) FROM structured_offers WHERE COALESCE({$expr}, '') <> ''")
            ->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
        $stmt->execute([':name' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        $rows = $this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
        foreach ($rows as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }
        return false;
    }
}
