<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm5e2(string $v):string{
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu',' ',$v)??$v;
    $v=preg_replace('/\bq\s*\d{1,2}\b/iu',' ',$v)??$v;
    $v=preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:a|e|k|plat|arm(?:brace)?s?)\b/iu',' ',$v)??$v;
    $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}

$aliases=[];
foreach($db->query("SELECT * FROM parser_learned_aliases WHERE active=1 ORDER BY confidence DESC,id") as $r){
    $aliases[(string)$r['normalized_alias']][]=$r;
}

$groups=$db->query("
SELECT * FROM parser_residual_groups
WHERE decision='keep_unresolved'
ORDER BY offer_count DESC,id")->fetchAll(PDO::FETCH_ASSOC);

$saveGroup=$db->prepare("
UPDATE parser_residual_groups
SET decision='correct_item',corrected_item=?,corrected_key=?,notes=?,reviewed_at=?,updated_at=?
WHERE id=? AND decision='keep_unresolved'");

$saveMembers=$db->prepare("
UPDATE parser_residual_reviews
SET decision='correct_item',corrected_item=?,corrected_key=?,notes=?,reviewed_at=?,updated_at=?
WHERE id IN (SELECT review_id FROM parser_residual_group_members WHERE group_id=?)
AND decision='keep_unresolved'");

$resolved=0;$offers=0;
foreach($groups as $g){
    $keys=[];
    foreach([
        norm5e2((string)$g['item_sample']),
        norm5e2((string)$g['segment_sample'])
    ] as $n){
        if($n===''||!isset($aliases[$n]))continue;
        foreach($aliases[$n] as $a)$keys[(string)$a['item_key']]=$a;
    }
    if(count($keys)!==1)continue;

    $a=array_values($keys)[0];

    // miniature guardrail
    if(str_starts_with(mb_strtolower((string)$a['item_name']),'miniature ')
       && !preg_match('/\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu',(string)$g['segment_sample'])){
        continue;
    }

    $now=gmdate('c');
    $note='Phase 5E auto: trusted learned alias '.$a['alias'];

    $db->beginTransaction();
    try{
        $saveGroup->execute([$a['item_name'],$a['item_key'],$note,$now,$now,$g['id']]);
        $saveMembers->execute([$a['item_name'],$a['item_key'],$note,$now,$now,$g['id']]);
        $db->commit();
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }

    $resolved++;$offers+=(int)$g['offer_count'];
}
echo "Phase 5E learned-alias resolve klaar.\n";
echo "Groups resolved: {$resolved}\n";
echo "Offers resolved: {$offers}\n";
echo "Rapport: php tools/maintenance/phase5e/report.php\n";
