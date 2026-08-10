<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function has(string $text,string $pattern):bool{
    return (bool)preg_match($pattern,$text);
}

$rows=$db->query("
SELECT *
FROM parser_residual_groups
WHERE decision IS NULL
ORDER BY offer_count DESC,id
")->fetchAll(PDO::FETCH_ASSOC);

$saveGroup=$db->prepare("
UPDATE parser_residual_groups
SET decision=?,corrected_item=?,corrected_key=?,notes=?,reviewed_at=?,updated_at=?
WHERE id=? AND decision IS NULL");

$saveMembers=$db->prepare("
UPDATE parser_residual_reviews
SET decision=?,corrected_item=?,corrected_key=?,notes=?,reviewed_at=?,updated_at=?
WHERE id IN (
  SELECT review_id
  FROM parser_residual_group_members
  WHERE group_id=?
)
AND decision IS NULL");

$counts=[];
$autoCount=0;
$left=0;

foreach($rows as $g){
    $item=mb_strtolower(trim((string)$g['item_sample']));
    $seg=mb_strtolower(trim((string)$g['segment_sample']));
    $text=trim($item.' '.$seg);

    $suggestions=json_decode((string)$g['suggested_json'],true)?:[];

    $decision=null;
    $correctedItem=null;
    $correctedKey=null;
    $note=null;

    // -------------------------------------------------------------
    // 1) Clearly insufficient identity / generic collection
    // -------------------------------------------------------------
    if (
        has($text,'/\bpre[- ]?nerf\b.*\b(?:staffs?|hammers?|bows?|weapons?|items?)\b/iu')
        || has($text,'/\b(?:q|r)\s*[0-9]+\b.*\b(?:staffs?|hammers?|bows?|weapons?|items?)\b/iu')
        || has($text,'/\b(?:gold|green|white|purple)\s+(?:weapons?|items?|minis?)\b/iu')
        || has($text,'/\b(?:destroyer weapons|green staffs|gold value weapons)\b/iu')
        || has($text,'/\b(?:300\+\s*)?(?:elite|normal)\s*\/\s*(?:normal|elite)\s+tomes?\b/iu')
        || has($text,'/\breg tomes?\b.*\b(?:no|left)\b/iu')
    ){
        $decision='insufficient';
        $note='Phase 5C auto: broad family/collection without concrete catalogue identity.';
    }

    // -------------------------------------------------------------
    // 2) Bundle/list patterns
    // -------------------------------------------------------------
    elseif (
        has($text,'/\b(?:kath\s+\d+(?:a)?\/set|nich set\b|d core set\b)/iu')
        || has($text,'/\b(?:kuun|destroyer|varesh)\b.*\b(?:kuun|destroyer|varesh)\b/iu')
        || has($text,'/\b(?:elixir.+diamonds|firewater.+spam|bottom left\/right map piece)\b/iu')
        || has($text,'/\btrade for hero\/strat boxes\b/iu')
    ){
        $decision='bundle';
        $note='Phase 5C auto: multiple products/package/list in one residual segment.';
    }

    // -------------------------------------------------------------
    // 3) Modifier fragments
    // -------------------------------------------------------------
    elseif (
        has($text,'/\b(?:30\s*hp|45\s*hp.*ench|armor\s+\+?\s*[0-9]+|20\/20|40\/40)\b/iu')
        || has($text,'/\b(?:leadership|soul reaping|\bsr\b)\s*\+?\s*[345]\b/iu')
        || has($text,'/\b(?:staffhead|bowgrip|inscription)\b/iu')
    ){
        $decision='modifier';
        $note='Phase 5C auto: modifier/build fragment, not a standalone concrete item.';
    }

    // -------------------------------------------------------------
    // 4) Service/noise
    // -------------------------------------------------------------
    elseif (
        has($text,'/\b(?:running|ferry|service|trade me @ chest|summoners?)\b/iu')
        || has($text,'/\bprohecies,\s*factions\b/iu')
    ){
        $decision='service';
        $note='Phase 5C auto: service/chat context rather than item identity.';
    }

    // -------------------------------------------------------------
    // 5) Miniature variant/context
    // -------------------------------------------------------------
    elseif (
        has($text,'/\bminiature\b/iu')
        && !has($seg,'/\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu')
    ){
        $decision='miniature_variant';
        $note='Phase 5C auto: miniature without reliable ded/unded variant context.';
    }

    // -------------------------------------------------------------
    // 6) High-confidence exact suggestion -> correct_item
    //    Only if there is exactly one score 1.0 suggestion.
    // -------------------------------------------------------------
    else {
        $exact=array_values(array_filter($suggestions,fn($s)=>(float)($s['score']??0)>=0.9999));
        $exactByKey=[];
        foreach($exact as $s){
            if(!empty($s['key']))$exactByKey[(string)$s['key']]=$s;
        }

        if(count($exactByKey)===1){
            $pick=array_values($exactByKey)[0];

            // Guardrails: no auto-accept for vague/general wording.
            $blocked =
                has($text,'/\b(?:set|package|all|any|many|collection|weapons?|items?|tomes?)\b/iu')
                || has($text,'/\b(?:pre[- ]?nerf|old school|gold value)\b/iu');

            if(!$blocked){
                $decision='correct_item';
                $correctedItem=(string)$pick['name'];
                $correctedKey=(string)$pick['key'];
                $note='Phase 5C auto: single exact catalogue suggestion with no broad-list guardrail.';
            }
        }
    }

    // -------------------------------------------------------------
    // 7) Default: keep unresolved instead of guessing.
    // -------------------------------------------------------------
    if($decision===null){
        $decision='keep_unresolved';
        $note='Phase 5C auto: ambiguous residual; intentionally not guessed.';
        $left++;
    } else {
        $autoCount++;
    }

    $now=gmdate('c');

    $db->beginTransaction();
    try{
        $saveGroup->execute([
            $decision,$correctedItem,$correctedKey,$note,$now,$now,$g['id']
        ]);
        $saveMembers->execute([
            $decision,$correctedItem,$correctedKey,$note,$now,$now,$g['id']
        ]);
        $db->commit();
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }

    $counts[$decision]=($counts[$decision]??0)+1;
}

echo "Phase 5C automatische groepsclassificatie klaar.\n";
echo "Groepen verwerkt: ".count($rows)."\n";
echo "Automatisch beslist: {$autoCount}\n";
echo "Bewust unresolved gelaten: {$left}\n\n";

arsort($counts);
foreach($counts as $decision=>$count){
    printf("%-24s %d groups\n",$decision,$count);
}

echo "\nRapport:\n";
echo "  php tools/maintenance/phase5c/report.php\n";
echo "Export:\n";
echo "  php tools/maintenance/phase5b/export.php\n";
