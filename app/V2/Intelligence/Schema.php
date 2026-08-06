<?php

declare(strict_types=1);

namespace LittyWatch\V2\Intelligence;

use PDO;

final class Schema
{
    public static function ensure(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS market_intelligence (
    market_key TEXT PRIMARY KEY,
    item TEXT NOT NULL,
    best_wtb_ecto REAL NULL,
    best_wts_ecto REAL NULL,
    median_wtb_ecto REAL NULL,
    median_wts_ecto REAL NULL,
    spread_ecto REAL NULL,
    buy_offers INTEGER NOT NULL DEFAULT 0,
    sell_offers INTEGER NOT NULL DEFAULT 0,
    unique_traders INTEGER NOT NULL DEFAULT 0,
    liquidity_score INTEGER NOT NULL DEFAULT 0,
    demand_score INTEGER NOT NULL DEFAULT 50,
    confidence_score INTEGER NOT NULL DEFAULT 0,
    deal_score INTEGER NOT NULL DEFAULT 0,
    quality_label TEXT NOT NULL DEFAULT 'Onvoldoende data',
    liquidity_label TEXT NOT NULL DEFAULT 'Laag',
    demand_label TEXT NOT NULL DEFAULT 'Neutraal',
    last_activity TEXT NULL,
    updated_at TEXT NOT NULL
)
SQL);

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_market_intelligence_score ON market_intelligence(deal_score DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_market_intelligence_activity ON market_intelligence(last_activity DESC)');
    }
}
