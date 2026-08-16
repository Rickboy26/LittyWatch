<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.4 FIX2 target residuals ===\n";
foreach(['tea','frostfire','beacon','margo','wd grab','little john'] as $q){
    $s=db()->prepare("
        SELECT quality_status,quality_reason,item,raw_segment,COUNT(*) n
        FROM structured_offers
        WHERE LOWER(raw_segment) LIKE ?
        GROUP BY quality_status,quality_reason,item,raw_segment
        ORDER BY n DESC
        LIMIT 20
    ");
    $s->execute(['%'.$q.'%']);
    echo "\n[$q]\n";
    foreach($s as $r){
        printf("%4d | %-9s | %-28s | %-28s | %s\n",
            $r['n'],$r['quality_status'],$r['quality_reason'],$r['item'],$r['raw_segment']);
    }
}

echo "\n=== catalog_first_unresolved top ===\n";
$sql="SELECT item,raw_segment,COUNT(*) n
FROM structured_offers
WHERE quality_reason='catalog_first_unresolved'
GROUP BY item,raw_segment
ORDER BY n DESC LIMIT 50";
foreach(db()->query($sql) as $r){
    printf("%4d | %-32s | %s\n",$r['n'],$r['item'],$r['raw_segment']);
}
