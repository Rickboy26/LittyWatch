<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Canonical collision checks ===\n";
$sql="
SELECT
 m.message,so.item,so.item_key,so.normalized_market_key,so.quality_status,so.quality_reason
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE LOWER(m.message) LIKE '%prince rurik%'
   OR LOWER(m.message) LIKE '%undead prince%'
   OR LOWER(m.message) LIKE '%zhang%'
ORDER BY so.id DESC
LIMIT 100
";
foreach(db()->query($sql) as $r){
 echo "MSG: ".$r['message'].PHP_EOL;
 echo " -> ".$r['item']." | ".$r['item_key']." | ".$r['normalized_market_key']
    ." | ".$r['quality_status']." | ".$r['quality_reason'].PHP_EOL.PHP_EOL;
}

echo "=== Accepted official non-Miniature-prefix miniatures zonder dedication ===\n";
$sql="
SELECT COUNT(*)
FROM structured_offers
WHERE LOWER(item) IN (
 'white rabbit',
 'black moa chick',
 'brown rabbit',
 'gwen doll',
 'the frog',
 'the frog [halloween]',
 'the frog [wintersday]'
)
AND quality_status='accepted'
AND normalized_market_key NOT LIKE '%|dedication:dedicated%'
AND normalized_market_key NOT LIKE '%|dedication:undedicated%'
";
echo "Aantal: ".(int)db()->query($sql)->fetchColumn().PHP_EOL;
