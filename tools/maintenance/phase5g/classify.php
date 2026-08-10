<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function has5g(string $t,string $p):bool{return (bool)preg_match($p,$t);}

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
WHERE id IN (
 SELECT review_id FROM parser_residual_group_members WHERE group_id=?
)
AND decision='keep_unresolved'");

$counts=[];$offers=[];

foreach($groups as $g){
    $item=mb_strtolower(trim((string)$g['item_sample']));
    $seg=mb_strtolower(trim((string)$g['segment_sample']));
    $text=trim($item.' '.$seg);
    $decision=null;$note=null;

    // Clear modifier/stat-only remnants.
    if(
        has5g($text,'/\b(?:of the necro|necro mod for bow|cripple for bow|zealou?s?)\b/iu')
        || has5g($text,'/\b(?:\+5\s*sr|single mod|single-mod)\b/iu')
    ){
        $decision='modifier';
        $note='Phase 5G auto: clear upgrade/modifier shorthand.';
    }

    // Clear bundles / category requests.
    elseif(
        has5g($text,'/\b(?:prot monk weapons|green earth.*staffs?|nick items?.*per set|drunkard\/sweet stacks|nf pcons)\b/iu')
        || has5g($text,'/\b(?:dhuum minis?)\b.*:/iu')
        || has5g($text,'/\b(?:q9\/10\/11|q9\/11\/12)\b/iu')
    ){
        $decision='bundle';
        $note='Phase 5G auto: multi-item/category residual.';
    }

    // Too vague to become one catalogue identity.
    elseif(
        has5g($text,'/\b(?:topaz|darkhorn|arctic|etern|dwarfes?|pkt|hours?)\b/iu')
        || has5g($text,'/\b(?:celestial \(os\)|zodiac \(os\)|unidend golds?)\b/iu')
        || has5g($text,'/\bthey(?:\'re| are) great companions\b/iu')
    ){
        $decision='insufficient';
        $note='Phase 5G auto: shorthand too vague for unique identity.';
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

echo "Phase 5G classifier klaar.\n";
foreach($counts as $k=>$v){
    printf("%-16s groups=%d offers=%d\n",$k,$v,$offers[$k]??0);
}
echo "Volgende stap: php tools/maintenance/phase5g/dry-run.php\n";
