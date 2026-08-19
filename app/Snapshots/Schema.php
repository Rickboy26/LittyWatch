<?php

declare(strict_types=1);

namespace LittyWatch\Snapshots;

use PDO;

final class Schema
{
    public static function ensure(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS watchlist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    market_key TEXT NOT NULL UNIQUE,
    label TEXT,
    target_buy_ecto REAL,
    target_sell_ecto REAL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        self::ensureColumn($pdo, 'watchlist', 'target_buy_ecto', 'REAL');
        self::ensureColumn($pdo, 'watchlist', 'target_sell_ecto', 'REAL');
        self::ensureColumn($pdo, 'watchlist', 'updated_at', "TEXT NOT NULL DEFAULT ''");
        $pdo->exec("UPDATE watchlist SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL OR updated_at = ''");

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS market_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    market_key TEXT NOT NULL,
    best_wtb_ecto REAL,
    best_wts_ecto REAL,
    median_wtb_ecto REAL,
    median_wts_ecto REAL,
    active_offers INTEGER NOT NULL DEFAULT 0,
    unique_traders INTEGER NOT NULL DEFAULT 0,
    captured_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_market_snapshots_key_time ON market_snapshots(market_key, captured_at)');
        if (self::tableExists($pdo, 'structured_offers')) {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_structured_market_active ON structured_offers(normalized_market_key, lifecycle_status, quality_status)');
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
        $stmt->execute([':table' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
        foreach ($columns as $existing) {
            if (($existing['name'] ?? '') === $column) {
                return;
            }
        }
        $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }
}
