<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function has5h(string $t,string $p):bool{return (bool)preg_match($p,$t);}

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
        has5h($text,'/\b(?:vampire\s*5\/1|enchant\s*20%|marksmanship\s*\+1\s*20%|speargrip\s*\+5armor|10ar\s+vs\s+slash|of the warrior)\b/iu')
        || has5h($text,'/\b(?:ritualist\s*\(5spawn\)|mesmer\s*\(5fc\))\b/iu')
    ){
        $decision='modifier';
        $note='Phase 5H auto: clear upgrade/attribute fragment.';
    }
    elseif(
        has5h($text,'/\b(?:map pieces?|hero armor|insignias?|green necromancer|gold value items?|unident(?:ified)? golds?|q9 os)\b/iu')
        || has5h($text,'/\b(?:strat zaishen box|alcohol stack 3pt|alc stacks?)\b/iu')
    ){
        $decision='insufficient';
        $note='Phase 5H auto: category/currency shorthand not one unique concrete item.';
    }
    elseif(
        has5h($text,'/\b(?:warband of brother run|nordmenn som vil ha armbånd)\b/iu')
    ){
        $decision='service';
        $note='Phase 5H auto: service/non-item residual.';
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

echo "Phase 5H classifier klaar.\n";
foreach($counts as $k=>$v){
    printf("%-16s groups=%d offers=%d\n",$k,$v,$offers[$k]??0);
}
echo "Volgende stap: php tools/maintenance/phase5h/dry-run.php\n";
