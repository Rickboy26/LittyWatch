<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm5d(string $v):string{
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu',' ',$v)??$v;
    $v=preg_replace('/\bq\s*\d{1,2}\b/iu',' ',$v)??$v;
    $v=preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:a|e|k|plat|arm(?:brace)?s?)\b/iu',' ',$v)??$v;
    $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function variants5d(string $text):array{
    $n=norm5d($text); if($n==='')return [];
    $vars=[$n];
    $rules=[
        '/\bel\b/u'=>'everlasting','/\bmini\b/u'=>'miniature','/\bminipet\b/u'=>'miniature',
        '/\bghero\b/u'=>'ghostly hero','/\bobs?i\b/u'=>'obsidian','/\bfow\b/u'=>'fissure of woe',
        '/\bnico\b/u'=>'nicholas','/\bdy\b/u'=>'dye','/\bglad\b/u'=>'gladiator',
        '/\bstrat\b/u'=>'strategist','/\bchamp\b/u'=>'champion','/\bsin\b/u'=>'assassin',
        '/\bnecro\b/u'=>'necromancer','/\bmes\b/u'=>'mesmer','/\bpara\b/u'=>'paragon',
        '/\bderv\b/u'=>'dervish','/\brit\b/u'=>'ritualist','/\bele\b/u'=>'elementalist',
        '/\bwar\b/u'=>'warrior','/\brang\b/u'=>'ranger'
    ];
    $e=$n;
    foreach($rules as $p=>$r)$e=preg_replace($p,$r,$e)??$e;
    $e=trim(preg_replace('/\s+/u',' ',$e)??$e);
    if($e!==$n)$vars[]=$e;
    if(preg_match('/s$/u',$n))$vars[]=preg_replace('/s$/u','',$n)??$n;
    return array_values(array_unique(array_filter($vars)));
}
function tokens5d(string $v):array{
    return array_values(array_unique(array_filter(explode(' ',norm5d($v)),fn($t)=>mb_strlen($t)>=2)));
}
function score5d(string $q,string $c):float{
    $qn=norm5d($q);$cn=norm5d($c);
    if($qn===''||$cn==='')return 0.0;
    if($qn===$cn)return 1.0;

    $qt=tokens5d($qn);$ct=tokens5d($cn);
    $inter=count(array_intersect($qt,$ct));
    if($inter===0)return 0.0;
    $union=count(array_unique(array_merge($qt,$ct)));
    $jac=$union?($inter/$union):0.0;

    similar_text($qn,$cn,$pct);
    $sim=($pct/100)*0.9;
    $contains=(str_contains($cn,$qn)||str_contains($qn,$cn))?0.92:0.0;

    return min(1.0,max($jac,$sim,$contains));
}
function autoBlock5d(array $group,array $top): ?string{
    $item=mb_strtolower(trim((string)$group['item_sample']));
    $seg=mb_strtolower(trim((string)$group['segment_sample']));
    $topName=mb_strtolower(trim((string)$top['name']));

    // Generic tome identities must never become concrete catalog matches.
    if(in_array($topName,['elite tome','normal tome','tome'],true)){
        return 'generic_tome';
    }

    // Miniatures require explicit ded/unded context.
    if(str_starts_with($topName,'miniature ')
        && !preg_match('/\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu',$seg)){
        return 'miniature_variant_missing';
    }

    // Very short market shorthands are too ambiguous for auto-accept.
    $itemNorm=norm5d($item);
    if(mb_strlen(str_replace(' ','',$itemNorm))<=3){
        return 'short_alias';
    }

    // Broad/list wording still blocks auto-accept.
    if(preg_match('/\b(?:set|package|all|any|many|collection|weapons?|items?|tomes?)\b/iu',$item.' '.$seg)){
        return 'broad_list';
    }

    return null;
}

// Build catalogue + token index once.
$catalog=[];
$tokenIndex=[];
$exactIndex=[];

foreach($db->query("SELECT key,name FROM kb_items WHERE active=1") as $r){
    $key=(string)$r['key'];$name=(string)$r['name'];
    $catalog[$key]=['key'=>$key,'name'=>$name,'strings'=>[$name]];
}
foreach($db->query("
SELECT a.item_key,a.alias
FROM kb_aliases a
JOIN kb_items i ON i.key=a.item_key
WHERE i.active=1
") as $r){
    $key=(string)$r['item_key'];
    if(isset($catalog[$key]))$catalog[$key]['strings'][]=(string)$r['alias'];
}
foreach($catalog as $key=>&$row){
    $row['strings']=array_values(array_unique(array_filter($row['strings'])));
    $row['norms']=[];
    foreach($row['strings'] as $s){
        $n=norm5d($s); if($n==='')continue;
        $row['norms'][]=$n;
        $exactIndex[$n][$key]=true;
        foreach(tokens5d($n) as $t)$tokenIndex[$t][$key]=true;
    }
}
unset($row);

$groups=$db->query("
SELECT * FROM parser_residual_groups
WHERE decision='keep_unresolved'
ORDER BY offer_count DESC,id
")->fetchAll(PDO::FETCH_ASSOC);

echo "Catalogus geladen: ".count($catalog)." items\n";
echo "Keep-unresolved groups: ".count($groups)."\n";

$results=[];$done=0;$total=count($groups);

foreach($groups as $g){
    $queries=array_values(array_unique(array_merge(
        variants5d((string)$g['item_sample']),
        variants5d((string)$g['segment_sample'])
    )));

    $candidateKeys=[];
    foreach($queries as $q){
        if(isset($exactIndex[$q])){
            foreach(array_keys($exactIndex[$q]) as $k)$candidateKeys[$k]=true;
        }
        foreach(tokens5d($q) as $t){
            foreach(array_keys($tokenIndex[$t]??[]) as $k)$candidateKeys[$k]=true;
        }
    }

    $scores=[];
    foreach(array_keys($candidateKeys) as $key){
        $best=0.0;$via='name';
        foreach($queries as $q){
            foreach($catalog[$key]['norms'] as $idx=>$cand){
                $s=score5d($q,$cand);
                if($s>$best){$best=$s;$via=$idx===0?'name':'alias';}
            }
        }
        if($best>=0.86){
            $scores[]=[
                'key'=>$key,
                'name'=>$catalog[$key]['name'],
                'score'=>round(min(1.0,$best),4),
                'via'=>$via
            ];
        }
    }

    usort($scores,fn($a,$b)=>$b['score']<=>$a['score']);
    $top=$scores[0]??null;$second=$scores[1]??null;
    $confidence='NONE';$apply=false;$blockedReason=null;

    if($top){
        $blockedReason=autoBlock5d($g,$top);
        $margin=$second?($top['score']-$second['score']):1.0;

        if($blockedReason===null && $top['score']>=0.97 && $margin>=0.08){
            $confidence='HIGH';$apply=true;
        } elseif($top['score']>=0.92 && $margin>=0.12){
            $confidence='MEDIUM';
        } else {
            $confidence='LOW';
        }
    }

    $results[]=[
        'group_id'=>(int)$g['id'],
        'item_sample'=>(string)$g['item_sample'],
        'segment_sample'=>(string)$g['segment_sample'],
        'offer_count'=>(int)$g['offer_count'],
        'top'=>$top,
        'second'=>$second,
        'confidence'=>$confidence,
        'apply'=>$apply,
        'blocked_reason'=>$blockedReason,
    ];

    $done++;
    if($done%25===0||$done===$total)echo "Voortgang: {$done}/{$total}\n";
}

$outDir=dirname(__DIR__,3).'/data/exports';
if(!is_dir($outDir))mkdir($outDir,0775,true);
$path=$outDir.'/littywatch-phase5d-dryrun-'.date('Ymd-His').'.json';
file_put_contents($path,json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

$high=array_values(array_filter($results,fn($r)=>$r['confidence']==='HIGH'));
$blocked=array_values(array_filter($results,fn($r)=>!empty($r['blocked_reason'])));

echo "Phase 5D FIX2 dry-run klaar.\n";
echo "HIGH candidates: ".count($high)."\n";
echo "Blocked candidates: ".count($blocked)."\n";
echo "Rapportbestand: {$path}\n\n";

echo "=== TOP HIGH CANDIDATES ===\n";
foreach(array_slice($high,0,80) as $r){
    printf("#%-5d %-30s -> %-35s [%s] score %.2f x%d\n",
        $r['group_id'],$r['item_sample'],$r['top']['name'],$r['top']['key'],$r['top']['score'],$r['offer_count']);
}

echo "\n=== TOP BLOCKED CANDIDATES ===\n";
foreach(array_slice($blocked,0,40) as $r){
    printf("#%-5d %-30s -> %-35s reason=%s score %.2f x%d\n",
        $r['group_id'],$r['item_sample'],$r['top']['name'],$r['blocked_reason'],$r['top']['score'],$r['offer_count']);
}
