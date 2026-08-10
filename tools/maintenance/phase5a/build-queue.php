<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm5a(string $v): string {
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}

// Load catalogue ONCE.
$catalog=[];
$nameIndex=[];
$aliasIndex=[];

foreach($db->query("SELECT key,name FROM kb_items WHERE active=1") as $r){
    $key=(string)$r['key'];
    $name=(string)$r['name'];
    $norm=norm5a($name);
    $tokens=array_values(array_filter(explode(' ',$norm),fn($t)=>mb_strlen($t)>=3));
    $catalog[$key]=[
        'key'=>$key,
        'name'=>$name,
        'norm'=>$norm,
        'tokens'=>$tokens,
    ];
    if($norm!=='') $nameIndex[$norm][]=$key;
}

foreach($db->query("
    SELECT a.item_key,a.normalized_alias
    FROM kb_aliases a
    JOIN kb_items i ON i.key=a.item_key
    WHERE i.active=1
") as $r){
    $norm=trim((string)$r['normalized_alias']);
    if($norm!=='') $aliasIndex[$norm][]=(string)$r['item_key'];
}

function suggest5a_fast(string $item,array $catalog,array $nameIndex,array $aliasIndex): array {
    $q=norm5a($item);
    if($q==='') return [];

    $exactKeys=array_values(array_unique(array_merge(
        $nameIndex[$q]??[],
        $aliasIndex[$q]??[]
    )));

    if($exactKeys){
        $out=[];
        foreach($exactKeys as $key){
            if(!isset($catalog[$key])) continue;
            $out[]=[
                'key'=>$key,
                'name'=>$catalog[$key]['name'],
                'score'=>1.0,
                'via'=>isset($nameIndex[$q])&&in_array($key,$nameIndex[$q],true)?'name':'alias',
            ];
        }
        return array_slice($out,0,5);
    }

    $tokens=array_values(array_filter(explode(' ',$q),fn($t)=>mb_strlen($t)>=3));
    if(!$tokens) return [];

    $out=[];
    foreach($catalog as $row){
        $nt=$row['tokens'];
        if(!$nt) continue;

        $intersection=count(array_intersect($tokens,$nt));
        if($intersection===0) continue; // major speed-up

        $union=count(array_unique(array_merge($tokens,$nt)));
        $jaccard=$union?($intersection/$union):0.0;

        // Only run similar_text on plausible token-overlap candidates.
        $similarity=0.0;
        if($jaccard>=0.20){
            similar_text($q,$row['norm'],$pct);
            $similarity=($pct/100)*0.85;
        }

        $score=max($jaccard,$similarity);
        if($score<0.58) continue;

        $out[]=[
            'key'=>$row['key'],
            'name'=>$row['name'],
            'score'=>round($score,4),
            'via'=>'fuzzy',
        ];
    }

    usort($out,fn($a,$b)=>$b['score']<=>$a['score']);
    return array_slice($out,0,5);
}

$reasons=[
 'catalog_first_unresolved',
 'miniature_variant_unresolved',
 'miniature_context_conflict',
 'modifier_fragment_unresolved',
 'collection_or_market_request',
 'service_or_noise',
 'insufficient_item_identity'
];

$ph=implode(',',array_fill(0,count($reasons),'?'));

$sql="SELECT
 so.id structured_offer_id,
 so.message_id,
 so.item,
 so.raw_segment,
 so.quality_reason,
 m.message raw_message
FROM structured_offers so
LEFT JOIN messages m ON m.id=so.message_id
WHERE so.lifecycle_status='rejected'
  AND so.quality_reason IN ($ph)
ORDER BY
 CASE so.quality_reason
  WHEN 'catalog_first_unresolved' THEN 0
  WHEN 'miniature_variant_unresolved' THEN 1
  WHEN 'miniature_context_conflict' THEN 2
  WHEN 'modifier_fragment_unresolved' THEN 3
  ELSE 4
 END,
 so.id";

$q=$db->prepare($sql);
$q->execute($reasons);
$rows=$q->fetchAll(PDO::FETCH_ASSOC);
$total=count($rows);

$up=$db->prepare("INSERT INTO parser_residual_reviews(
 structured_offer_id,message_id,item,raw_segment,raw_message,current_reason,
 suggested_json,created_at,updated_at
)VALUES(:sid,:mid,:item,:seg,:msg,:reason,:suggest,:created,:updated)
ON CONFLICT(structured_offer_id) DO UPDATE SET
 message_id=excluded.message_id,
 item=excluded.item,
 raw_segment=excluded.raw_segment,
 raw_message=excluded.raw_message,
 current_reason=excluded.current_reason,
 suggested_json=CASE
   WHEN parser_residual_reviews.decision IS NULL THEN excluded.suggested_json
   ELSE parser_residual_reviews.suggested_json
 END,
 updated_at=excluded.updated_at");

echo "Catalogus geladen: ".count($catalog)." items\n";
echo "Residual rows: {$total}\n";

$done=0;
$db->beginTransaction();

try{
    foreach($rows as $r){
        $suggest=suggest5a_fast((string)$r['item'],$catalog,$nameIndex,$aliasIndex);
        $now=gmdate('c');

        $up->execute([
            ':sid'=>$r['structured_offer_id'],
            ':mid'=>$r['message_id'],
            ':item'=>$r['item'],
            ':seg'=>$r['raw_segment'],
            ':msg'=>$r['raw_message'],
            ':reason'=>$r['quality_reason'],
            ':suggest'=>json_encode($suggest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ':created'=>$now,
            ':updated'=>$now,
        ]);

        $done++;
        if($done%250===0 || $done===$total){
            echo "Voortgang: {$done}/{$total}\n";
        }

        // Periodically commit to avoid holding one huge write transaction.
        if($done%1000===0 && $done<$total){
            $db->commit();
            $db->beginTransaction();
        }
    }

    if($db->inTransaction()) $db->commit();
}catch(Throwable $e){
    if($db->inTransaction()) $db->rollBack();
    throw $e;
}

$count=(int)$db->query("SELECT COUNT(*) FROM parser_residual_reviews")->fetchColumn();
echo "Klaar. Review queue bevat {$count} rows.\n";
echo "Rapport: php tools/maintenance/phase5a/report.php\n";
