<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

echo "=== PHASE 6A CANDIDATE STATUS ===\n";
foreach($db->query("
SELECT status,COUNT(DISTINCT group_id) groups
FROM parser_green_alias_candidates
GROUP BY status
ORDER BY groups DESC
") as $r){
    printf("%-20s %d groups\n",$r['status'],$r['groups']);
}

echo "\n=== STRONG UNIQUE ===\n";
foreach($db->query("
SELECT group_id,alias,candidate_name,candidate_key,score
FROM parser_green_alias_candidates
WHERE status='strong_unique'
ORDER BY group_id,score DESC
") as $r){
    printf("#%-5d %-24s -> %-36s [%s] %.2f\n",
        $r['group_id'],$r['alias'],$r['candidate_name'],$r['candidate_key'],$r['score']);
}

echo "\n=== CURRENT RESIDUAL STATUS ===\n";
foreach($db->query("
SELECT COALESCE(decision,'OPEN') decision,COUNT(*) groups,SUM(offer_count) offers
FROM parser_residual_groups
GROUP BY COALESCE(decision,'OPEN')
ORDER BY offers DESC
") as $r){
    printf("%-24s groups=%4d offers=%4d\n",$r['decision'],$r['groups'],$r['offers']);
}
