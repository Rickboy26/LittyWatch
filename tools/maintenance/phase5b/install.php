<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$db->exec("
CREATE TABLE IF NOT EXISTS parser_residual_groups (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 signature TEXT NOT NULL UNIQUE,
 normalized_item TEXT NOT NULL,
 normalized_segment TEXT NOT NULL,
 primary_reason TEXT NOT NULL,
 item_sample TEXT NOT NULL,
 segment_sample TEXT,
 offer_count INTEGER NOT NULL DEFAULT 0,
 message_count INTEGER NOT NULL DEFAULT 0,
 suggested_json TEXT,
 decision TEXT,
 corrected_item TEXT,
 corrected_key TEXT,
 notes TEXT,
 reviewed_at TEXT,
 created_at TEXT NOT NULL,
 updated_at TEXT NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS parser_residual_group_members (
 group_id INTEGER NOT NULL,
 review_id INTEGER NOT NULL UNIQUE,
 PRIMARY KEY(group_id,review_id)
)");

$db->exec("CREATE INDEX IF NOT EXISTS idx_parser_residual_groups_decision ON parser_residual_groups(decision)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_parser_residual_groups_reason ON parser_residual_groups(primary_reason)");

echo "OK: Phase 5B schema geïnstalleerd.\n";
echo "Volgende stap: php tools/maintenance/phase5b/build-groups.php\n";
