<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.4 FIX1 unresolved residuals ===\n";
foreach(['tea','frostfire','beacon','margo','wd grab','little john','alc','ghoti','celestal'] as $q){
    $s=db()->prepare("
        SELECT item,raw_segment,quality_reason,COUNT(*) n
        FROM structured_offers
        WHERE LOWER(raw_segment) LIKE ?
        GROUP BY item,raw_segment,quality_reason
        ORDER BY n DESC
        LIMIT 15
    ");
    $s->execute(['%'.$q.'%']);
    echo "\n[$q]\n";
    foreach($s as $r){
        printf("%4d | %-28s | %-30s | %s\n",$r['n'],$r['quality_reason'],$r['item'],$r['raw_segment']);
    }
}
