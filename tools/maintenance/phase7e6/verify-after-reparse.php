<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.6 slash-list residuals ===\n";

$st=db()->query("
SELECT
    m.message,
    so.item,
    so.item_key,
    so.market_key,
    so.raw_segment,
    so.quality_status,
    so.quality_reason,
    so.lifecycle_status,
    COUNT(*) OVER () AS total_rows
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE LOWER(m.message) LIKE '%zhed%livia%'
ORDER BY m.id DESC,so.id
LIMIT 150
");

$bad=0;
$rows=0;
foreach($st as $r){
    $rows++;
    if(
        (string)$r['quality_reason']==='catalog_first_unresolved'
        || (string)$r['quality_reason']==='miniature_variant_unresolved'
        || mb_strtolower(trim((string)$r['item']))==='miniature'
    ) $bad++;

    echo "MSG: ".$r['message'].PHP_EOL;
    echo " -> ".$r['item']
        ." | ".$r['market_key']
        ." | ".$r['quality_status']
        ." | ".$r['quality_reason']
        ." | ".$r['raw_segment']
        .PHP_EOL.PHP_EOL;
}

echo "Getoonde rows: {$rows}\n";
echo "Residual/problem rows: {$bad}\n";

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
