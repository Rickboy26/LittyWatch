<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm5d(string $v): string {
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu',' ',$v)??$v;
    $v=preg_replace('/\bq\s*\d{1,2}\b/iu',' ',$v)??$v;
    $v=preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:a|e|k|plat|arm(?:brace)?s?)\b/iu',' ',$v)??$v;
    $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}

function variants5d(string $text): array {
    $n=norm5d($text);
    if($n==='')return [];

    $vars=[$n];

    $rules=[
        '/\bel\b/u' => 'everlasting',
        '/\bmini\b/u' => 'miniature',
        '/\bminipet\b/u' => 'miniature',
        '/\bghero\b/u' => 'ghostly hero',
        '/\bobs?i\b/u' => 'obsidian',
        '/\bfow\b/u' => 'fissure of woe',
        '/\bnico\b/u' => 'nicholas',
        '/\bdy\b/u' => 'dye',
        '/\bglad\b/u' => 'gladiator',
        '/\bstrat\b/u' => 'strategist',
        '/\bchamp\b/u' => 'champion',
        '/\bsin\b/u' => 'assassin',
        '/\bnecro\b/u' => 'necromancer',
        '/\bmes\b/u' => 'mesmer',
        '/\bpara\b/u' => 'paragon',
        '/\bderv\b/u' => 'dervish',
        '/\brit\b/u' => 'ritualist',
        '/\bele\b/u' => 'elementalist',
        '/\bwar\b/u' => 'warrior',
        '/\brang\b/u' => 'ranger',
    ];

    $expanded=$n;
    foreach($rules as $pat=>$rep)$expanded=preg_replace($pat,$rep,$expanded)??$expanded;
    $expanded=trim(preg_replace('/\s+/u',' ',$expanded)??$expanded);
    if($expanded!==$n)$vars[]=$expanded;

    // common plural cleanup
    if(preg_match('/s$/u',$n))$vars[]=preg_replace('/s$/u','',$n)??$n;

    return array_values(array_unique(array_filter($vars)));
}

function score5d(string $query,string $candidate): float {
    $q=norm5d($query);
    $c=norm5d($candidate);
    if($q===''||$c==='')return 0.0;
    if($q===$c)return 1.0;

    $qt=array_values(array_filter(explode(' ',$q),fn($t)=>mb_strlen($t)>=2));
    $ct=array_values(array_filter(explode(' ',$c),fn($t)=>mb_strlen($t)>=2));
    $inter=count(array_intersect($qt,$ct));
    $union=count(array_unique(array_merge($qt,$ct)));
    $jac=$union?($inter/$union):0.0;

    similar_text($q,$c,$pct);
    $sim=$pct/100;

    $contains=(str_contains($c,$q)||str_contains($q,$c))?0.92:0.0;

    return max($jac,$sim*0.9,$contains);
}

$catalog=[];
$aliasMap=[];
foreach($db->query("SELECT key,name FROM kb_items WHERE active=1") as $r){
    $catalog[(string)$r['key']]=['key'=>(string)$r['key'],'name'=>(string)$r['name']];
}
foreach($db->query("
SELECT a.item_key,a.alias
FROM kb_aliases a
JOIN kb_items i ON i.key=a.item_key
WHERE i.active=1
") as $r){
    $aliasMap[(string)$r['item_key']][]=(string)$r['alias'];
}

$groups=$db->query("
SELECT *
FROM parser_residual_groups
WHERE decision='keep_unresolved'
ORDER BY offer_count DESC,id
")->fetchAll(PDO::FETCH_ASSOC);

$candidates=[];
foreach($groups as $g){
    $queries=array_values(array_unique(array_merge(
        variants5d((string)$g['item_sample']),
        variants5d((string)$g['segment_sample'])
    )));

    $scores=[];
    foreach($catalog as $key=>$row){
        $best=0.0;$via='name';
        foreach($queries as $q){
            $s=score5d($q,$row['name']);
            if($s>$best){$best=$s;$via='name';}
            foreach($aliasMap[$key]??[] as $alias){
                $sa=score5d($q,$alias);
                if($sa>$best){$best=$sa;$via='alias';}
            }
        }
        if($best>=0.86){
            $scores[]=['key'=>$key,'name'=>$row['name'],'score'=>round($best,4),'via'=>$via];
        }
    }

    usort($scores,fn($a,$b)=>$b['score']<=>$a['score']);
    $top=$scores[0]??null;
    $second=$scores[1]??null;

    $confidence='NONE';
    $apply=false;
    if($top){
        $margin=$second?($top['score']-$second['score']):1.0;
        if($top['score']>=0.97 && $margin>=0.08){$confidence='HIGH';$apply=true;}
        elseif($top['score']>=0.92 && $margin>=0.12){$confidence='MEDIUM';}
        else{$confidence='LOW';}
    }

    $candidates[]=[
        'group_id'=>(int)$g['id'],
        'item_sample'=>(string)$g['item_sample'],
        'segment_sample'=>(string)$g['segment_sample'],
        'offer_count'=>(int)$g['offer_count'],
        'top'=>$top,
        'second'=>$second,
        'confidence'=>$confidence,
        'apply'=>$apply,
    ];
}

$outDir=dirname(__DIR__,3).'/data/exports';
if(!is_dir($outDir))mkdir($outDir,0775,true);
$path=$outDir.'/littywatch-phase5d-dryrun-'.date('Ymd-His').'.json';
file_put_contents($path,json_encode($candidates,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

$high=array_values(array_filter($candidates,fn($r)=>$r['confidence']==='HIGH'));
$medium=array_values(array_filter($candidates,fn($r)=>$r['confidence']==='MEDIUM'));

echo "Phase 5D dry-run klaar.\n";
echo "Keep-unresolved groups: ".count($groups)."\n";
echo "HIGH candidates: ".count($high)."\n";
echo "MEDIUM candidates: ".count($medium)."\n";
echo "Rapportbestand: {$path}\n\n";

echo "=== TOP HIGH CANDIDATES ===\n";
foreach(array_slice($high,0,80) as $r){
    printf("#%-5d %-30s -> %-35s [%s] score %.2f x%d\n",
        $r['group_id'],$r['item_sample'],$r['top']['name'],$r['top']['key'],$r['top']['score'],$r['offer_count']
    );
}
