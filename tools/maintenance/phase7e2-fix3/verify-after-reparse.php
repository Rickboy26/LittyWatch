<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

echo "=== Miniatures zonder dedication die toch accepted zijn ===\n";
$sql = "
SELECT COUNT(*) AS aantal
FROM structured_offers
WHERE lower(item) LIKE 'miniature %'
  AND quality_status='accepted'
  AND normalized_market_key NOT LIKE '%|dedication:dedicated%'
  AND normalized_market_key NOT LIKE '%|dedication:undedicated%'
";
$count = (int)db()->query($sql)->fetchColumn();
echo "Aantal: {$count}\n\n";

echo "=== Gerichte 7E.2 voorbeelden ===\n";
$sql = "
SELECT
    m.message,
    so.item,
    so.normalized_market_key,
    so.lifecycle_status,
    so.quality_status,
    so.quality_reason
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE
       LOWER(m.message) LIKE '%kuuna%'
    OR LOWER(m.message) LIKE '%rift warden%'
    OR LOWER(m.message) LIKE '%rift war%'
    OR LOWER(m.message) LIKE '%ghero%'
    OR LOWER(m.message) LIKE '%mini dhuum%'
ORDER BY so.id DESC
LIMIT 100
";
foreach (db()->query($sql) as $r) {
    echo "MSG: ".$r['message'].PHP_EOL;
    echo " -> item=".($r['item'] ?? '-')
       ." | market=".($r['normalized_market_key'] ?? '-')
       ." | lifecycle=".($r['lifecycle_status'] ?? '-')
       ." | quality=".($r['quality_status'] ?? '-')
       ." | reason=".($r['quality_reason'] ?? '-')
       .PHP_EOL.PHP_EOL;
}
