<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

echo "=== PHASE 5C AUTO DECISIONS ===\n";
foreach($db->query("
SELECT COALESCE(decision,'OPEN') decision,COUNT(*) groups,SUM(offer_count) offers
FROM parser_residual_groups
GROUP BY COALESCE(decision,'OPEN')
ORDER BY offers DESC
") as $r){
    printf("%-24s groups=%4d offers=%4d\n",$r['decision'],$r['groups'],$r['offers']);
}

echo "\n=== KEEP_UNRESOLVED TOP ===\n";
foreach($db->query("
SELECT id,item_sample,segment_sample,offer_count,message_count
FROM parser_residual_groups
WHERE decision='keep_unresolved'
ORDER BY offer_count DESC,id
LIMIT 80
") as $r){
    printf("#%-5d %-42s offers=%3d msgs=%3d\n",
        $r['id'],$r['item_sample'],$r['offer_count'],$r['message_count']
    );
    echo "      ".$r['segment_sample']."\n";
}

echo "\n=== AUTO CORRECT_ITEM TOP ===\n";
foreach($db->query("
SELECT id,item_sample,corrected_item,corrected_key,offer_count
FROM parser_residual_groups
WHERE decision='correct_item'
ORDER BY offer_count DESC,id
LIMIT 50
") as $r){
    printf("#%-5d %-32s -> %-32s [%s] x%d\n",
        $r['id'],$r['item_sample'],$r['corrected_item'],$r['corrected_key'],$r['offer_count']
    );
}
