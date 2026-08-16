<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Ghostly Hero strongbox collisions ===\n";
$st=db()->query("
SELECT
    m.message,
    so.item,
    so.market_key,
    so.quality_status,
    so.quality_reason,
    so.lifecycle_status
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE LOWER(m.message) LIKE '%ghostly%hero%strongbox%'
ORDER BY so.id DESC
LIMIT 100
");
$bad=0;
foreach($st as $r){
    if(mb_strtolower(trim((string)$r['item']))==='miniature ghostly hero')$bad++;
    echo "MSG: ".$r['message'].PHP_EOL;
    echo " -> ".$r['item']." | ".$r['market_key']." | ".$r['quality_status']." | ".$r['quality_reason']." | ".$r['lifecycle_status'].PHP_EOL.PHP_EOL;
}
echo "Miniature Ghostly Hero collisions: {$bad}\n";

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
