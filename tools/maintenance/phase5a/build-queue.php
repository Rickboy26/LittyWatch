<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm5a(string $v):string{
 $v=mb_strtolower(trim($v));
 $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
 $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
 return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function suggest5a(PDO $db,string $item):array{
 $q=norm5a($item);
 if($q==='')return [];
 $st=$db->prepare("SELECT i.key,i.name,'name' via FROM kb_items i
  WHERE i.active=1 AND lower(trim(i.name))=lower(trim(?))
  UNION ALL
  SELECT i.key,i.name,'alias' via FROM kb_aliases a
  JOIN kb_items i ON i.key=a.item_key
  WHERE i.active=1 AND a.normalized_alias=?
  LIMIT 10");
 $st->execute([$item,$q]);
 $out=[];
 foreach($st as $r)$out[$r['key']]=['key'=>$r['key'],'name'=>$r['name'],'score'=>1.0,'via'=>$r['via']];
 if($out)return array_values($out);

 $tokens=array_values(array_filter(explode(' ',$q),fn($t)=>mb_strlen($t)>=3));
 if(!$tokens)return [];
 foreach($db->query("SELECT key,name FROM kb_items WHERE active=1") as $r){
   $n=norm5a((string)$r['name']); if($n==='')continue;
   $nt=array_values(array_filter(explode(' ',$n),fn($t)=>mb_strlen($t)>=3));
   if(!$nt)continue;
   $inter=count(array_intersect($tokens,$nt));
   $union=count(array_unique(array_merge($tokens,$nt)));
   $j=$union?($inter/$union):0.0;
   similar_text($q,$n,$pct);
   $score=max($j,($pct/100)*0.85);
   if($score<0.58)continue;
   $out[$r['key']]=['key'=>$r['key'],'name'=>$r['name'],'score'=>round($score,4),'via'=>'fuzzy'];
 }
 usort($out,fn($a,$b)=>$b['score']<=>$a['score']);
 return array_slice(array_values($out),0,5);
}

$reasons=[
 'catalog_first_unresolved','miniature_variant_unresolved','miniature_context_conflict',
 'modifier_fragment_unresolved','collection_or_market_request','service_or_noise',
 'insufficient_item_identity'
];
$ph=implode(',',array_fill(0,count($reasons),'?'));

$sql="SELECT so.id structured_offer_id,so.message_id,so.item,so.raw_segment,so.quality_reason,m.message raw_message
FROM structured_offers so
LEFT JOIN messages m ON m.id=so.message_id
WHERE so.lifecycle_status='rejected'
AND so.quality_reason IN ($ph)
ORDER BY CASE so.quality_reason
 WHEN 'catalog_first_unresolved' THEN 0
 WHEN 'miniature_variant_unresolved' THEN 1
 WHEN 'miniature_context_conflict' THEN 2
 WHEN 'modifier_fragment_unresolved' THEN 3
 ELSE 4 END, so.id";
$q=$db->prepare($sql);$q->execute($reasons);

$up=$db->prepare("INSERT INTO parser_residual_reviews(
 structured_offer_id,message_id,item,raw_segment,raw_message,current_reason,suggested_json,created_at,updated_at
)VALUES(:sid,:mid,:item,:seg,:msg,:reason,:suggest,:created,:updated)
ON CONFLICT(structured_offer_id) DO UPDATE SET
 message_id=excluded.message_id,item=excluded.item,raw_segment=excluded.raw_segment,
 raw_message=excluded.raw_message,current_reason=excluded.current_reason,
 suggested_json=CASE WHEN parser_residual_reviews.decision IS NULL THEN excluded.suggested_json ELSE parser_residual_reviews.suggested_json END,
 updated_at=excluded.updated_at");

$count=0;
foreach($q as $r){
 $now=gmdate('c');
 $up->execute([
  ':sid'=>$r['structured_offer_id'],':mid'=>$r['message_id'],':item'=>$r['item'],
  ':seg'=>$r['raw_segment'],':msg'=>$r['raw_message'],':reason'=>$r['quality_reason'],
  ':suggest'=>json_encode(suggest5a($db,(string)$r['item']),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
  ':created'=>$now,':updated'=>$now
 ]);
 $count++;
}
echo "Phase 5A review queue opgebouwd: {$count} residual rows.\n";
echo "Review starten: php tools/maintenance/phase5a/review.php\n";
