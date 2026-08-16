<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);require $root.'/bootstrap.php';
echo "=== Phase 7E.4 catalog_first_unresolved residuals ===\n";
foreach(['alc','tea','frostfire','beacon','margo','wd grab','little john','ghoti','celestal'] as $q){
 $s=db()->prepare("SELECT item,raw_segment,COUNT(*) n FROM structured_offers WHERE quality_reason='catalog_first_unresolved' AND LOWER(raw_segment) LIKE ? GROUP BY item,raw_segment ORDER BY n DESC LIMIT 15");$s->execute(['%'.$q.'%']);
 echo "\n[$q]\n";foreach($s as $r)printf("%4d | %-30s | %s\n",$r['n'],$r['item'],$r['raw_segment']);
}
