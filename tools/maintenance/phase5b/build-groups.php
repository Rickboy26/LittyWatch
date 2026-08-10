<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm5b(string $v): string {
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:a|e|k|plat|arm(?:brace)?s?)\b/iu',' <price> ',$v)??$v;
    $v=preg_replace('/\bq\s*\d{1,2}\b/iu',' <q> ',$v)??$v;
    $v=preg_replace('/\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu',' <mini_state> ',$v)??$v;
    $v=preg_replace('/[^a-z0-9<>\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}

function signature5b(string $item,string $segment,string $reason): array {
    $ni=norm5b($item);
    $ns=norm5b($segment);
    $sig=hash('sha256',$reason."\n".$ni."\n".$ns);
    return [$sig,$ni,$ns];
}

$rows=$db->query("
SELECT *
FROM parser_residual_reviews
WHERE decision IS NULL
  AND current_reason='catalog_first_unresolved'
ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

$groups=[];
foreach($rows as $r){
    [$sig,$ni,$ns]=signature5b((string)$r['item'],(string)($r['raw_segment']??''));
    if(!isset($groups[$sig])){
        $groups[$sig]=[
            'signature'=>$sig,
            'normalized_item'=>$ni,
            'normalized_segment'=>$ns,
            'primary_reason'=>(string)$r['current_reason'],
            'item_sample'=>(string)$r['item'],
            'segment_sample'=>(string)($r['raw_segment']??''),
            'message_ids'=>[],
            'review_ids'=>[],
            'suggested_json'=>(string)($r['suggested_json']??'[]'),
        ];
    }
    $groups[$sig]['review_ids'][]=(int)$r['id'];
    if(!empty($r['message_id']))$groups[$sig]['message_ids'][(int)$r['message_id']]=true;
}

$up=$db->prepare("
INSERT INTO parser_residual_groups(
 signature,normalized_item,normalized_segment,primary_reason,item_sample,segment_sample,
 offer_count,message_count,suggested_json,created_at,updated_at
) VALUES(
 :signature,:normalized_item,:normalized_segment,:primary_reason,:item_sample,:segment_sample,
 :offer_count,:message_count,:suggested_json,:created_at,:updated_at
)
ON CONFLICT(signature) DO UPDATE SET
 normalized_item=excluded.normalized_item,
 normalized_segment=excluded.normalized_segment,
 primary_reason=excluded.primary_reason,
 item_sample=excluded.item_sample,
 segment_sample=excluded.segment_sample,
 offer_count=excluded.offer_count,
 message_count=excluded.message_count,
 suggested_json=CASE WHEN parser_residual_groups.decision IS NULL THEN excluded.suggested_json ELSE parser_residual_groups.suggested_json END,
 updated_at=excluded.updated_at
");

$find=$db->prepare("SELECT id FROM parser_residual_groups WHERE signature=?");
$link=$db->prepare("INSERT OR IGNORE INTO parser_residual_group_members(group_id,review_id) VALUES(?,?)");

$db->beginTransaction();
try{
    $done=0;$total=count($groups);
    foreach($groups as $g){
        $now=gmdate('c');
        $up->execute([
            ':signature'=>$g['signature'],
            ':normalized_item'=>$g['normalized_item'],
            ':normalized_segment'=>$g['normalized_segment'],
            ':primary_reason'=>$g['primary_reason'],
            ':item_sample'=>$g['item_sample'],
            ':segment_sample'=>$g['segment_sample'],
            ':offer_count'=>count($g['review_ids']),
            ':message_count'=>count($g['message_ids']),
            ':suggested_json'=>$g['suggested_json'],
            ':created_at'=>$now,
            ':updated_at'=>$now,
        ]);
        $find->execute([$g['signature']]);
        $gid=(int)$find->fetchColumn();
        foreach($g['review_ids'] as $rid)$link->execute([$gid,$rid]);
        $done++;
        if($done%100===0||$done===$total)echo "Voortgang: {$done}/{$total} groepen\n";
    }
    $db->commit();
}catch(Throwable $e){
    if($db->inTransaction())$db->rollBack();
    throw $e;
}

echo "Klaar. ".count($groups)." unieke catalog-backlog groepen opgebouwd.\n";
echo "Rapport: php tools/maintenance/phase5b/report.php\n";
echo "Review:  php tools/maintenance/phase5b/review.php\n";
