<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.5 FIX5 post-reparse checks ===\n";

$bad=db()->query("
SELECT COUNT(*)
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE LOWER(m.message) LIKE '%ghostly%hero%strongbox%'
  AND LOWER(TRIM(so.item))='miniature ghostly hero'
")->fetchColumn();
echo "Ghostly Hero Strongbox -> Miniature collisions: ".(int)$bad.PHP_EOL;

$alc=db()->query("
SELECT COUNT(*) FROM structured_offers
WHERE LOWER(raw_segment) LIKE '%stacks of alc%'
  AND item='Alcohol Points'
  AND quality_status='accepted'
")->fetchColumn();
echo "Accepted stacks-of-alc Alcohol Points: ".(int)$alc.PHP_EOL;

$livia=db()->query("
SELECT COUNT(*) FROM structured_offers
WHERE item='Miniature Livia'
  AND market_key LIKE '%dedication:%'
  AND quality_status='accepted'
")->fetchColumn();
echo "Accepted dedicated Miniature Livia rows: ".(int)$livia.PHP_EOL;

echo "\n=== catalog_first_unresolved top ===\n";
foreach(db()->query("
SELECT item,raw_segment,COUNT(*) n
FROM structured_offers
WHERE quality_reason='catalog_first_unresolved'
GROUP BY item,raw_segment
ORDER BY n DESC
LIMIT 50
") as $r){
    printf("%4d | %-32s | %s\n",$r['n'],$r['item'],$r['raw_segment']);
}
