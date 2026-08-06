CREATE TABLE IF NOT EXISTS watchlist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    market_key TEXT NOT NULL,
    label TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(market_key)
);

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
);
CREATE INDEX IF NOT EXISTS idx_market_snapshots_key_time ON market_snapshots(market_key, captured_at);

CREATE TABLE IF NOT EXISTS alert_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    market_key TEXT NOT NULL,
    rule_type TEXT NOT NULL,
    threshold REAL,
    currency TEXT DEFAULT 'e',
    enabled INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS v2_settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
