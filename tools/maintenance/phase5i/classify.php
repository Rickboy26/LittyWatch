<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function has5i(string $t,string $p):bool{return (bool)preg_match($p,$t);}

$groups=$db->query("
SELECT * FROM parser_residual_groups
WHERE decision='keep_unresolved'
ORDER BY offer_count DESC,id
")->fetchAll(PDO::FETCH_ASSOC);

$saveGroup=$db->prepare("
UPDATE parser_residual_groups
SET decision=?,notes=?,reviewed_at=?,updated_at=?
WHERE id=? AND decision='keep_unresolved'");

$saveMembers=$db->prepare("
UPDATE parser_residual_reviews
SET decision=?,notes=?,reviewed_at=?,updated_at=?
WHERE id IN (SELECT review_id FROM parser_residual_group_members WHERE group_id=?)
AND decision='keep_unresolved'");

$counts=[];$offers=[];

foreach($groups as $g){
    $item=mb_strtolower(trim((string)$g['item_sample']));
    $seg=mb_strtolower(trim((string)$g['segment_sample']));
    $text=trim($item.' '.$seg);
    $decision=null;$note=null;

    if(
        has5i($text,'/\b(?:marksmanship\s*\+1\s*20%|enchant\s*20%|es\+5 bow|5e \/ \+15%|15\^50)\b/iu')
        || has5i($text,'/\b(?:ritualist\s*\(5spawn\)|mesmer\s*\(5fc\))\b/iu')
    ){
        $decision='modifier';
        $note='Phase 5I auto: upgrade/modifier fragment.';
    }
    elseif(
        has5i($text,'/\b(?:tahlkora|morgahn|dunkoro|zenmai|olias|sousuke|hayda)\b.*[,]/iu')
        || has5i($text,'/\b(?:miniture|miniature)\b.*(?:jora|burning titan|mandragor imp|kirin).*,/iu')
        || has5i($text,'/\beblades?\b.*q9.*11/iu')
    ){
        $decision='bundle';
        $note='Phase 5I auto: multi-item/list residual.';
    }
    elseif(
        has5i($text,'/\b(?:thank you|attitude not aptitude|per 250|alcohol)\b/iu')
        || has5i($text,'/\b(?:golden staffs unident|green necromancer)\b/iu')
    ){
        $decision='insufficient';
        $note='Phase 5I auto: vague/non-unique market residual.';
    }

    if($decision===null)continue;

    $now=gmdate('c');
    $db->beginTransaction();
    try{
        $saveGroup->execute([$decision,$note,$now,$now,$g['id']]);
        $saveMembers->execute([$decision,$note,$now,$now,$g['id']]);
        $db->commit();
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }

    $counts[$decision]=($counts[$decision]??0)+1;
    $offers[$decision]=($offers[$decision]??0)+(int)$g['offer_count'];
}

echo "Phase 5I classifier klaar.\n";
foreach($counts as $k=>$v){
    printf("%-16s groups=%d offers=%d\n",$k,$v,$offers[$k]??0);
}
echo "Volgende stap: php tools/maintenance/phase5i/dry-run-items.php\n";
