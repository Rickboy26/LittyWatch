<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

echo "=== PHASE 6B STATUS ===\n";
foreach($db->query("
SELECT COALESCE(decision,'OPEN') decision,COUNT(*) groups,SUM(offer_count) offers
FROM parser_residual_groups
GROUP BY COALESCE(decision,'OPEN')
ORDER BY offers DESC") as $r){
    printf("%-24s groups=%4d offers=%4d\n",$r['decision'],$r['groups'],$r['offers']);
}

echo "\n=== PHASE 6B LEARNED ===\n";
foreach($db->query("
SELECT alias,item_name,item_key,confidence,source_group_id
FROM parser_learned_aliases
WHERE source='phase6b_context_green' AND active=1
ORDER BY confidence DESC,id") as $r){
    printf("#%-5s %-24s -> %-36s [%s] %.2f\n",
        $r['source_group_id'],$r['alias'],$r['item_name'],$r['item_key'],$r['confidence']);
}
