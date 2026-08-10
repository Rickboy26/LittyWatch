<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$db->exec("
CREATE TABLE IF NOT EXISTS parser_green_alias_candidates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    alias TEXT NOT NULL,
    normalized_alias TEXT NOT NULL,
    candidate_key TEXT NOT NULL,
    candidate_name TEXT NOT NULL,
    score REAL NOT NULL,
    evidence_json TEXT,
    status TEXT NOT NULL DEFAULT 'candidate',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(group_id,candidate_key)
)");

$db->exec("CREATE INDEX IF NOT EXISTS idx_parser_green_alias_candidates_group ON parser_green_alias_candidates(group_id)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_parser_green_alias_candidates_status ON parser_green_alias_candidates(status)");

echo "OK: Phase 6A schema geïnstalleerd.\n";
echo "Volgende stap: php tools/maintenance/phase6a/dry-run.php\n";
