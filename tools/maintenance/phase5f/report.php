<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

echo "=== PHASE 5F STATUS ===\n";
foreach($db->query("
SELECT COALESCE(decision,'OPEN') decision,COUNT(*) groups,SUM(offer_count) offers
FROM parser_residual_groups
GROUP BY COALESCE(decision,'OPEN')
ORDER BY offers DESC") as $r){
    printf("%-24s groups=%4d offers=%4d\n",$r['decision'],$r['groups'],$r['offers']);
}

echo "\n=== REMAINING KEEP_UNRESOLVED ===\n";
foreach($db->query("
SELECT id,item_sample,segment_sample,offer_count
FROM parser_residual_groups
WHERE decision='keep_unresolved'
ORDER BY offer_count DESC,id
LIMIT 80") as $r){
    printf("#%-5d %-40s x%d\n",$r['id'],$r['item_sample'],$r['offer_count']);
    echo "      ".$r['segment_sample']."\n";
}
