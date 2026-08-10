<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function has5k(string $t,string $p):bool{return (bool)preg_match($p,$t);}

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
    $text=$item.' '.$seg;
    $decision=null;$note=null;

    if(
        has5k($text,'/\bmarksmanship\s*\+1\s*20%\b/iu')
        || has5k($text,'/\benchant\s*20%\b/iu')
        || has5k($text,'/\britualist\s*\(5spawn\)\b/iu')
        || has5k($text,'/\bmesmer\s*\(5fc\)\b/iu')
        || has5k($text,'/\b20%\s*enchanting grip\b/iu')
        || has5k($text,'/\bany elemental dager tang\b/iu')
    ){
        $decision='modifier';
        $note='Phase 5K auto: deterministic modifier fragment.';
    }
    elseif(
        has5k($text,'/\b(?:purple minipets?|minis?\s*\(60\+\)|mods and scripts|con sets|sin, mes, monk, nec elite t|normal and elite dervish sk)\b/iu')
        || has5k($text,'/\b(?:bday minis?|minipets?\s*\(\)|purple m)\b/iu')
    ){
        $decision='insufficient';
        $note='Phase 5K auto: deterministic collection/category shorthand.';
    }
    elseif(
        has5k($text,'/\b(?:open your wallet bro|pls help me clear my chest|dye it orange and your playin|\[ervice\])\b/iu')
    ){
        $decision='service';
        $note='Phase 5K auto: deterministic chat/noise residual.';
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

echo "Phase 5K classifier klaar.\n";
foreach($counts as $k=>$v){
    printf("%-16s groups=%d offers=%d\n",$k,$v,$offers[$k]??0);
}
echo "Volgende stap: php tools/maintenance/phase5k/dry-run.php\n";
