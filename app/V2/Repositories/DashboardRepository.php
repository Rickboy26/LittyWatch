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

        $sql = "SELECT market_key,
                       MIN(item) AS item,
                       COUNT(*) AS offers,
                       COUNT(DISTINCT player) AS traders,
                       MAX(COALESCE(observed_at, created_at, '')) AS latest
                FROM structured_offers
                WHERE COALESCE(market_key, '') <> ''
                  AND COALESCE(status, 'active') IN ('active', 'accepted')
                GROUP BY market_key
                ORDER BY offers DESC, latest DESC
                LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function latestOffers(int $limit): array
    {
        if ($this->tableExists('structured_offers')) {
            $sql = "SELECT trade_type, item, requirement, attribute, price_amount, price_currency, player,
                           COALESCE(observed_at, created_at, '') AS observed_at
                    FROM structured_offers
                    ORDER BY id DESC LIMIT :limit";
        } elseif ($this->tableExists('offers')) {
            $sql = "SELECT type AS trade_type, item, NULL AS requirement, NULL AS attribute,
                           price AS price_amount, currency AS price_currency, player,
                           COALESCE(created_at, '') AS observed_at
                    FROM offers ORDER BY id DESC LIMIT :limit";
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
        return (int) $this->pdo->query("SELECT COUNT(DISTINCT market_key) FROM structured_offers WHERE COALESCE(market_key, '') <> ''")->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
        $stmt->execute([':name' => $table]);
        return (bool) $stmt->fetchColumn();
    }
}
