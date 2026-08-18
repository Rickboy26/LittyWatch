<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

echo "=== Phase 7E.9 FIX2 live verification ===\n";

$falseMini = (int)db()->query("
SELECT COUNT(*)
FROM structured_offers
WHERE lower(COALESCE(raw_segment,'')) LIKE '%ghostly priest%'
  AND lower(COALESCE(item,'')) LIKE 'miniature ghostly priest%'
  AND lower(COALESCE(raw_segment,'')) NOT LIKE '%ded%'
  AND lower(COALESCE(raw_segment,'')) NOT LIKE '%unded%'
")->fetchColumn();

$tonic = (int)db()->query("
SELECT COUNT(*)
FROM structured_offers
WHERE lower(COALESCE(item,'')) LIKE '%ghostly priest%'
  AND lower(COALESCE(item,'')) LIKE '%tonic%'
")->fetchColumn();

echo "EL/bare Ghostly Priest false-miniature rows: {$falseMini}\n";
echo "Ghostly Priest tonic rows: {$tonic}\n";

echo $falseMini === 0
    ? "OK: Ghostly Priest tonic-context lekt niet meer naar miniature.\n"
    : "FAIL: false miniature bestaat nog.\n";

exit($falseMini === 0 ? 0 : 1);
