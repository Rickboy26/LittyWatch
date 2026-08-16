<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.5 FIX1 targeted checks ===\n";

foreach([
    'alc'=>'%stacks of alc%',
    'livia'=>'%unded/livia%',
    'ghostly strongbox'=>'%ghostly%strongbox%',
] as $label=>$like){
    $st=db()->prepare("
        SELECT quality_status,quality_reason,item,raw_segment,COUNT(*) n
        FROM structured_offers
        WHERE LOWER(raw_segment) LIKE ?
        GROUP BY quality_status,quality_reason,item,raw_segment
        ORDER BY n DESC
        LIMIT 30
    ");
    $st->execute([$like]);
    echo "\n[$label]\n";
    foreach($st as $r){
        printf("%4d | %-9s | %-28s | %-30s | %s\n",
            $r['n'],$r['quality_status'],$r['quality_reason'],$r['item'],$r['raw_segment']);
    }
}

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
