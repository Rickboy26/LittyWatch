<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$sql = "
SELECT
    m.message,
    so.item,
    so.item_key,
    so.normalized_market_key,
    so.lifecycle_status,
    so.quality_reason
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
WHERE
       LOWER(m.message) LIKE '%unded kuuna%'
    OR LOWER(m.message) LIKE '%ded kuuna%'
    OR LOWER(m.message) LIKE '%rift warden%'
    OR LOWER(m.message) LIKE '%rift war%'
    OR LOWER(m.message) LIKE '%dhuum%'
    OR LOWER(m.message) LIKE '%dsr%'
    OR LOWER(m.message) LIKE '%kath set%'
    OR LOWER(m.message) LIKE '%kath hammer%'
ORDER BY so.id DESC
LIMIT 150
";

foreach (db()->query($sql) as $r) {
    echo "MSG: " . $r['message'] . PHP_EOL;
    echo " -> item=" . ($r['item'] ?? '-')
       . " | key=" . ($r['item_key'] ?? '-')
       . " | market=" . ($r['normalized_market_key'] ?? '-')
       . " | lifecycle=" . ($r['lifecycle_status'] ?? '-')
       . " | reason=" . ($r['quality_reason'] ?? '-')
       . PHP_EOL . PHP_EOL;
}
