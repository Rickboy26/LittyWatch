<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$db->exec("
CREATE TABLE IF NOT EXISTS parser_learned_aliases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    alias TEXT NOT NULL,
    normalized_alias TEXT NOT NULL,
    item_key TEXT NOT NULL,
    item_name TEXT NOT NULL,
    source TEXT NOT NULL,
    source_group_id INTEGER,
    confidence REAL NOT NULL DEFAULT 1.0,
    active INTEGER NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(normalized_alias,item_key)
)");

$db->exec("CREATE INDEX IF NOT EXISTS idx_parser_learned_aliases_norm ON parser_learned_aliases(normalized_alias)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_parser_learned_aliases_active ON parser_learned_aliases(active)");

echo "OK: Phase 5E schema geïnstalleerd.\n";
echo "Volgende stap: php tools/maintenance/phase5e/dry-run.php\n";
