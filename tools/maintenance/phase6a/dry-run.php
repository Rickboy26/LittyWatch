<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm6a(string $v): string {
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function toks6a(string $v): array {
    return array_values(array_unique(array_filter(explode(' ',norm6a($v)),fn($t)=>mb_strlen($t)>=2)));
}
function abbr6a(string $name): string {
    $tokens=toks6a($name);
    $out='';
    foreach($tokens as $t){
        if(in_array($t,['of','the','and','for','a','an'],true))continue;
        $out.=mb_substr($t,0,1);
    }
    return $out;
}
function score6a(string $alias,string $candidate): array {
    $a=norm6a($alias);
    $c=norm6a($candidate);
    $at=toks6a($a);$ct=toks6a($c);
    $inter=count(array_intersect($at,$ct));
    $union=count(array_unique(array_merge($at,$ct)));
    $jac=$union?($inter/$union):0.0;

    similar_text($a,$c,$pct);
    $sim=$pct/100;

    $abbr=abbr6a($candidate);
    $aliasCompact=preg_replace('/[^a-z0-9]/u','',$a)??$a;
    $abbrScore=($abbr!=='' && $aliasCompact===$abbr)?1.0:0.0;

    $contains=(str_contains($c,$a)||str_contains($a,$c))?0.92:0.0;

    $score=max($jac,$sim*0.88,$abbrScore,$contains);
    return [min(1.0,$score),[
        'jaccard'=>round($jac,4),
        'similarity'=>round($sim,4),
        'abbreviation'=>$abbr,
        'abbr_match'=>$abbrScore===1.0,
        'contains'=>$contains>0
    ]];
}

$targets=$db->query("
SELECT id,item_sample,segment_sample,offer_count
FROM parser_residual_groups
WHERE decision='keep_unresolved'
  AND (
    lower(item_sample) IN (
      'bo dom curs','ghost spaw','outcast dom','plag illus','jade sp',
      'demrikov','primeval remna','curse of thul za','beautiful menzies','japa'
    )
    OR offer_count>=5
  )
ORDER BY offer_count DESC,id
")->fetchAll(PDO::FETCH_ASSOC);

$catalog=$db->query("
SELECT key,name
FROM kb_items
WHERE active=1
ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$aliasRows=$db->query("
SELECT a.item_key,a.alias
FROM kb_aliases a
JOIN kb_items i ON i.key=a.item_key
WHERE i.active=1
")->fetchAll(PDO::FETCH_ASSOC);

$aliasesByKey=[];
foreach($aliasRows as $r)$aliasesByKey[(string)$r['item_key']][]=(string)$r['alias'];

$db->exec("DELETE FROM parser_green_alias_candidates");

$ins=$db->prepare("
INSERT INTO parser_green_alias_candidates(
 group_id,alias,normalized_alias,candidate_key,candidate_name,score,evidence_json,status,created_at,updated_at
) VALUES(?,?,?,?,?,?,?,?,?,?)
");

$report=[];
foreach($targets as $g){
    $alias=(string)$g['item_sample'];
    $scores=[];

    foreach($catalog as $c){
        $best=0.0;$bestEvidence=[];$via='name';

        [$s,$ev]=score6a($alias,(string)$c['name']);
        if($s>$best){$best=$s;$bestEvidence=$ev;$via='name';}

        foreach($aliasesByKey[(string)$c['key']]??[] as $ka){
            [$sa,$eva]=score6a($alias,$ka);
            if($sa>$best){$best=$sa;$bestEvidence=$eva;$via='kb_alias';}
        }

        if($best<0.55)continue;

        $scores[]=[
            'key'=>(string)$c['key'],
            'name'=>(string)$c['name'],
            'score'=>round($best,4),
            'via'=>$via,
            'evidence'=>$bestEvidence
        ];
    }

    usort($scores,fn($a,$b)=>$b['score']<=>$a['score']);
    $top=array_slice($scores,0,5);

    $status='none';
    if(count($top)>=1){
        $margin=count($top)>=2?($top[0]['score']-$top[1]['score']):1.0;
        if($top[0]['score']>=0.97 && $margin>=0.12)$status='strong_unique';
        elseif($top[0]['score']>=0.85 && $margin>=0.15)$status='review';
        else $status='ambiguous';
    }

    foreach($top as $cand){
        $now=gmdate('c');
        $ins->execute([
            $g['id'],$alias,norm6a($alias),$cand['key'],$cand['name'],$cand['score'],
            json_encode([
                'via'=>$cand['via'],
                'evidence'=>$cand['evidence'],
                'group_offer_count'=>(int)$g['offer_count'],
                'segment'=>(string)$g['segment_sample'],
                'group_status'=>$status
            ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            $status,$now,$now
        ]);
    }

    $report[]=[
        'group_id'=>(int)$g['id'],
        'alias'=>$alias,
        'offers'=>(int)$g['offer_count'],
        'segment'=>(string)$g['segment_sample'],
        'status'=>$status,
        'top'=>$top
    ];
}

$outDir=dirname(__DIR__,3).'/data/exports';
if(!is_dir($outDir))mkdir($outDir,0775,true);
$path=$outDir.'/littywatch-phase6a-green-dryrun-'.date('Ymd-His').'.json';
file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

echo "Phase 6A green/unique dry-run klaar.\n";
echo "Groups analysed: ".count($report)."\n";
echo "Rapport: {$path}\n\n";

foreach($report as $r){
    echo "#{$r['group_id']} {$r['alias']} x{$r['offers']} status={$r['status']}\n";
    foreach($r['top'] as $i=>$c){
        printf("  %d. %-40s [%s] score %.2f via %s\n",
            $i+1,$c['name'],$c['key'],$c['score'],$c['via']);
    }
}
