<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$limit=isset($argv[1])?max(1,(int)$argv[1]):30;

$labels=$db->query("SELECT key,label,description FROM parser_review_labels ORDER BY sort_order,key")->fetchAll(PDO::FETCH_ASSOC);

$st=$db->prepare("
SELECT *
FROM parser_residual_groups
WHERE decision IS NULL
ORDER BY offer_count DESC,id
LIMIT ?");
$st->bindValue(1,$limit,PDO::PARAM_INT);
$st->execute();
$groups=$st->fetchAll(PDO::FETCH_ASSOC);

if(!$groups){echo "Geen open groepen.\n";exit;}

$examples=$db->prepare("
SELECT r.id,r.item,r.raw_segment,r.raw_message,r.message_id
FROM parser_residual_group_members gm
JOIN parser_residual_reviews r ON r.id=gm.review_id
WHERE gm.group_id=?
ORDER BY r.id
LIMIT 5");

$saveGroup=$db->prepare("
UPDATE parser_residual_groups
SET decision=?,corrected_item=?,corrected_key=?,notes=?,reviewed_at=?,updated_at=?
WHERE id=?");

$saveMember=$db->prepare("
UPDATE parser_residual_reviews
SET decision=?,corrected_item=?,corrected_key=?,notes=?,reviewed_at=?,updated_at=?
WHERE id IN (
 SELECT review_id FROM parser_residual_group_members WHERE group_id=?
)
AND decision IS NULL");

foreach($groups as $g){
    echo "\n============================================================\n";
    echo "Group #{$g['id']} | {$g['offer_count']} offers | {$g['message_count']} messages\n";
    echo "Item:    {$g['item_sample']}\n";
    echo "Segment: {$g['segment_sample']}\n";
    echo "Norm:    {$g['normalized_item']} || {$g['normalized_segment']}\n";

    $examples->execute([$g['id']]);
    echo "Voorbeelden:\n";
    foreach($examples as $e){
        echo "  - [msg {$e['message_id']}] {$e['raw_message']}\n";
    }

    $suggest=json_decode((string)$g['suggested_json'],true)?:[];
    if($suggest){
        echo "Suggestions:\n";
        foreach($suggest as $i=>$s)printf("  [S%d] %s [%s] %.2f via %s\n",$i+1,$s['name'],$s['key'],$s['score'],$s['via']);
    }

    echo "Labels:\n";
    foreach($labels as $i=>$l)printf("  [%d] %-22s %s\n",$i+1,$l['key'],$l['label']);
    echo "  [s] skip\n  [q] quit\n";

    $choice=strtolower(trim((string)readline("Keuze voor hele groep: ")));
    if($choice==='q')break;
    if($choice===''||$choice==='s')continue;

    $idx=(int)$choice-1;
    if(!isset($labels[$idx])){echo "Ongeldig; overgeslagen.\n";continue;}

    $decision=(string)$labels[$idx]['key'];
    $item=null;$key=null;
    if($decision==='correct_item'){
        if($suggest){
            $s=trim((string)readline("Suggestion nummer, Enter=handmatig: "));
            if($s!==''&&isset($suggest[(int)$s-1])){
                $pick=$suggest[(int)$s-1];$item=$pick['name'];$key=$pick['key'];
            }
        }
        if($item===null){
            $item=trim((string)readline("Correct item name: "));
            $key=trim((string)readline("Correct item key (optioneel): "));
            if($key==='')$key=null;
        }
    }

    $notes=trim((string)readline("Notitie (optioneel): "));
    if($notes==='')$notes=null;
    $now=gmdate('c');

    $db->beginTransaction();
    try{
        $saveGroup->execute([$decision,$item,$key,$notes,$now,$now,$g['id']]);
        $saveMember->execute([$decision,$item,$key,$notes,$now,$now,$g['id']]);
        $db->commit();
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }

    echo "Opgeslagen voor {$g['offer_count']} offers: {$decision}\n";
}
