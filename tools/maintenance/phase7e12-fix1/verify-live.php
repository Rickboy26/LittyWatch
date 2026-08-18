<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

echo "=== Phase 7E.12 FIX1 Alcohol Point verification ===\n";

$good = (int)db()->query("
    SELECT COUNT(*)
    FROM structured_offers
    WHERE item_key='alcohol-point'
      AND quality_reason='catalog_match'
")->fetchColumn();

$missing = (int)db()->query("
    SELECT COUNT(*)
    FROM structured_offers
    WHERE (
        lower(COALESCE(raw_segment,'')) LIKE '%alc stack%'
        OR lower(COALESCE(raw_segment,'')) LIKE '%1pt alc%'
        OR lower(COALESCE(raw_segment,'')) LIKE '%1point alch%'
    )
      AND quality_reason='strict_catalog_missing'
")->fetchColumn();

$unresolved = (int)db()->query("
    SELECT COUNT(*)
    FROM structured_offers
    WHERE (
        lower(COALESCE(raw_segment,'')) LIKE '%alc stack%'
        OR lower(COALESCE(raw_segment,'')) LIKE '%1pt alc%'
        OR lower(COALESCE(raw_segment,'')) LIKE '%1point alch%'
    )
      AND quality_reason='catalog_first_unresolved'
")->fetchColumn();

echo "Alcohol Point catalog_match: {$good}\n";
echo "Alcohol rows strict_catalog_missing: {$missing}\n";
echo "Alcohol rows catalog_first_unresolved: {$unresolved}\n";

echo ($missing===0 && $unresolved===0)
    ? "OK: Alcohol Point pipeline volledig canonical.\n"
    : "FAIL: Alcohol Point heeft nog rejects.\n";

exit(($missing===0 && $unresolved===0) ? 0 : 1);
