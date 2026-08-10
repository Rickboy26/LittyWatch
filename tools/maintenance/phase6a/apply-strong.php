<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$rows=$db->query("
SELECT c.*
FROM parser_green_alias_candidates c
JOIN (
    SELECT group_id,MAX(score) mx
    FROM parser_green_alias_candidates
    WHERE status='strong_unique'
    GROUP BY group_id
) x ON x.group_id=c.group_id AND x.mx=c.score
WHERE c.status='strong_unique'
ORDER BY c.group_id
")->fetchAll(PDO::FETCH_ASSOC);

$learn=$db->prepare("
INSERT INTO parser_learned_aliases(
 alias,normalized_alias,item_key,item_name,source,source_group_id,confidence,active,notes,created_at,updated_at
) VALUES(?,?,?,?,?,?,?,?,?,?,?)
ON CONFLICT(normalized_alias,item_key) DO UPDATE SET
 alias=excluded.alias,item_name=excluded.item_name,source=excluded.source,
 source_group_id=excluded.source_group_id,confidence=excluded.confidence,
 active=1,notes=excluded.notes,updated_at=excluded.updated_at
");

$count=0;
foreach($rows as $r){
    $now=gmdate('c');
    $learn->execute([
        $r['alias'],$r['normalized_alias'],$r['candidate_key'],$r['candidate_name'],
        'phase6a_green_unique',$r['group_id'],$r['score'],1,
        'Phase 6A strong_unique green/unique mapping',$now,$now
    ]);
    $count++;
}
echo "Phase 6A strong_unique aliases geactiveerd: {$count}\n";
echo "Draai nu: php tools/maintenance/phase5e/resolve-groups.php\n";
