<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

echo "=== PHASE 5B GROUP STATUS ===\n";
foreach($db->query("
SELECT COALESCE(decision,'OPEN') decision,COUNT(*) groepen,SUM(offer_count) offers
FROM parser_residual_groups
GROUP BY COALESCE(decision,'OPEN')
ORDER BY offers DESC
") as $r){
    printf("%-22s groups=%4d offers=%4d\n",$r['decision'],$r['groepen'],$r['offers']);
}

echo "\n=== TOP OPEN GROUPS ===\n";
foreach($db->query("
SELECT id,item_sample,segment_sample,offer_count,message_count
FROM parser_residual_groups
WHERE decision IS NULL
ORDER BY offer_count DESC,id
LIMIT 60
") as $r){
    printf("#%-5d %-42s offers=%3d msgs=%3d\n",$r['id'],$r['item_sample'],$r['offer_count'],$r['message_count']);
    echo "      ".$r['segment_sample']."\n";
}
