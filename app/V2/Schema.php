<?php

declare(strict_types=1);

namespace LittyWatch\V2;

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
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

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
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_structured_market_active ON structured_offers(normalized_market_key, lifecycle_status, quality_status)');
    }
}
