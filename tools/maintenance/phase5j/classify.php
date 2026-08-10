<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function has5j(string $t,string $p):bool{return (bool)preg_match($p,$t);}

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
        has5j($text,'/\b(?:marksmanship\s*\+1\s*20%|enchant\s*20%|es\+5 bow|double vampiric|vampire\s*5\/1|strength and honour)\b/iu')
        || has5j($text,'/\b(?:ritualist\s*\(5spawn\)|mesmer\s*\(5fc\))\b/iu')
    ){
        $decision='modifier';
        $note='Phase 5J auto: clear modifier/attribute residual.';
    }
    elseif(
        has5j($text,'/\b(?:cheap greens|bday minis|purple m|great[e]? cheap bows|please get these minis)\b/iu')
        || has5j($text,'/\b(?:green rock|bird|frosty|glob|el\b)\b/iu')
    ){
        $decision='insufficient';
        $note='Phase 5J auto: vague/non-unique category shorthand.';
    }
    elseif(
        has5j($text,'/\b(?:run asc|service|pst price|sorry brother|xD i can\'t do)\b/iu')
    ){
        $decision='service';
        $note='Phase 5J auto: service/chat noise.';
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

echo "Phase 5J classifier klaar.\n";
foreach($counts as $k=>$v){
    printf("%-16s groups=%d offers=%d\n",$k,$v,$offers[$k]??0);
}
echo "Volgende stap: php tools/maintenance/phase5j/dry-run.php\n";
