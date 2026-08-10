<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm6b1(string $v):string{
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/[^a-z0-9\'+~]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function attr6b1(string $text):?string{
    $map=[
        'domination magic'=>['domination','dom'],
        'illusion magic'=>['illusion','illus','illu'],
        'curses'=>['curses','curs'],
        'spawning power'=>['spawning power','spawning','spaw','sp'],
        'communing'=>['communing','comm'],
        'channeling magic'=>['channeling','channel','chan'],
        'restoration magic'=>['restoration','restor','resto'],
        'divine favor'=>['divine favor','divine','df'],
        'protection prayers'=>['protection','prot'],
        'smiting prayers'=>['smiting','smite'],
        'healing prayers'=>['healing','heal'],
        'soul reaping'=>['soul reaping','sr'],
        'death magic'=>['death magic','death'],
        'blood magic'=>['blood magic','blood'],
        'fire magic'=>['fire magic','fire'],
        'water magic'=>['water magic','water','h2o'],
        'air magic'=>['air magic','air'],
        'earth magic'=>['earth magic','earth'],
        'energy storage'=>['energy storage','es'],
        'fast casting'=>['fast casting','fc'],
        'inspiration magic'=>['inspiration','insp'],
        'leadership'=>['leadership'],
        'motivation'=>['motivation','moti'],
        'command'=>['command','com'],
        'tactics'=>['tactics','tac'],
        'strength'=>['strength','str'],
    ];
    $n=' '.norm6b1($text).' ';
    foreach($map as $canonical=>$aliases){
        foreach($aliases as $a){
            if(preg_match('/(?:^|\s)'.preg_quote($a,'/').'(?:\s|$)/iu',$n))return $canonical;
        }
    }
    return null;
}
function family6b1(string $text):?string{
    $n=norm6b1($text);
    $families=[
        'wand'=>['wand','scepter','sceptre'],
        'staff'=>['staff','staves'],
        'focus'=>['focus','offhand','off hand'],
        'shield'=>['shield','aegis'],
        'bow'=>['bow','flatbow','longbow','shortbow','hornbow','recurve'],
        'sword'=>['sword','blade','edge'],
        'axe'=>['axe'],
        'hammer'=>['hammer','maul'],
        'spear'=>['spear'],
        'daggers'=>['dagger','daggers','stilettos'],
        'scythe'=>['scythe'],
    ];
    foreach($families as $family=>$aliases){
        foreach($aliases as $a){
            if(preg_match('/\b'.preg_quote($a,'/').'\b/iu',$n))return $family;
        }
    }
    return null;
}
function isolateClause6b1(string $message,string $needle):string{
    $parts=preg_split('/\s*[~|]\s*/u',$message)?:[$message];
    $needleN=norm6b1($needle);
    $needleTokens=array_values(array_filter(explode(' ',$needleN)));
    $best='';$bestScore=-1;
    foreach($parts as $p){
        $pn=norm6b1($p);
        $score=0;
        foreach($needleTokens as $t){
            if($t!=='' && preg_match('/\b'.preg_quote($t,'/').'\b/iu',$pn))$score++;
        }
        if($score>$bestScore){$bestScore=$score;$best=trim($p);}
    }
    return $best!==''?$best:trim($message);
}
function skinTarget6b1(string $item):string{
    $n=norm6b1($item);
    $rules=[
        '/^bo\b/u'=>'bo staff',
        '/^ghost\b/u'=>'ghostly staff',
        '/^outcast\b/u'=>'outcast staff',
        '/^plag\b/u'=>'plagueborn staff',
        '/^jade\b/u'=>'jade staff',
    ];
    foreach($rules as $p=>$target){
        if(preg_match($p,$n))return $target;
    }
    return $n;
}
function nameFamily6b1(string $name):?string{return family6b1($name);}
function score6b1(string $target,string $candidate):float{
    $t=norm6b1($target);$c=norm6b1($candidate);
    if($t===''||$c==='')return 0.0;
    if($t===$c)return 1.0;
    if(str_contains($c,$t))return 0.98;
    similar_text($t,$c,$pct);
    return min(1.0,($pct/100)*0.9);
}

$targets=$db->query("
SELECT *
FROM parser_residual_groups
WHERE decision='keep_unresolved'
  AND lower(trim(item_sample)) IN (
    'bo dom curs','ghost spaw','outcast dom','plag illus','jade sp'
  )
ORDER BY offer_count DESC,id
")->fetchAll(PDO::FETCH_ASSOC);

$catalog=$db->query("
SELECT key,name
FROM kb_items
WHERE active=1
ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$getExamples=$db->prepare("
SELECT r.raw_message,r.raw_segment,r.item,r.message_id
FROM parser_residual_group_members gm
JOIN parser_residual_reviews r ON r.id=gm.review_id
WHERE gm.group_id=?
ORDER BY r.id
LIMIT 12");

$results=[];

foreach($targets as $g){
    $getExamples->execute([$g['id']]);
    $examples=$getExamples->fetchAll(PDO::FETCH_ASSOC);

    $clauses=[];
    foreach($examples as $e){
        $msg=(string)($e['raw_message']??'');
        if($msg!=='')$clauses[]=isolateClause6b1($msg,(string)$g['item_sample']);
    }
    if(!$clauses)$clauses[]=(string)$g['segment_sample'];

    // Most frequent isolated clause becomes canonical context.
    $freq=[];
    foreach($clauses as $c)$freq[$c]=($freq[$c]??0)+1;
    arsort($freq);
    $clause=(string)array_key_first($freq);

    $attribute=attr6b1($clause);
    if($attribute===null)$attribute=attr6b1((string)$g['item_sample']);

    // Parent message establishes staff family for this whole tilde-list.
    $family=family6b1($clause);
    if($family===null){
        foreach($examples as $e){
            $msg=(string)($e['raw_message']??'');
            if(preg_match('/\b(?:staves?|staffs?)\b/iu',$msg)){
                $family='staff';
                break;
            }
        }
    }

    $skinTarget=skinTarget6b1((string)$g['item_sample']);
    $cands=[];

    foreach($catalog as $c){
        $name=(string)$c['name'];
        $candFamily=nameFamily6b1($name);
        if($family!==null && $candFamily!==$family)continue;

        $score=score6b1($skinTarget,$name);
        if($score<0.70)continue;

        $cands[]=[
            'key'=>(string)$c['key'],
            'name'=>$name,
            'score'=>round($score,4),
            'family'=>$candFamily
        ];
    }

    usort($cands,fn($a,$b)=>$b['score']<=>$a['score']);
    $top=array_slice($cands,0,8);

    $status='none';
    if($top){
        $margin=count($top)>1?$top[0]['score']-$top[1]['score']:1.0;
        if($family!==null && $attribute!==null && $top[0]['score']>=0.98 && $margin>=0.08){
            $status='strong_context';
        }elseif($top[0]['score']>=0.90 && $margin>=0.08){
            $status='review';
        }else{
            $status='ambiguous';
        }
    }

    $results[]=[
        'group_id'=>(int)$g['id'],
        'item_sample'=>(string)$g['item_sample'],
        'offer_count'=>(int)$g['offer_count'],
        'isolated_clause'=>$clause,
        'skin_target'=>$skinTarget,
        'attribute'=>$attribute,
        'family'=>$family,
        'status'=>$status,
        'top'=>$top,
        'examples'=>array_slice($examples,0,5),
    ];
}

$outDir=dirname(__DIR__,3).'/data/exports';
if(!is_dir($outDir))mkdir($outDir,0775,true);
$path=$outDir.'/littywatch-phase6b-context-greens-'.date('Ymd-His').'.json';
file_put_contents($path,json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

echo "Phase 6B FIX1 clause-aware green dry-run klaar.\n";
echo "Groups analysed: ".count($results)."\n";
echo "Rapport: {$path}\n\n";

foreach($results as $r){
    echo "#{$r['group_id']} {$r['item_sample']} x{$r['offer_count']}\n";
    echo "  clause={$r['isolated_clause']}\n";
    echo "  skin={$r['skin_target']} attribute=".($r['attribute']??'-')." family=".($r['family']??'-')." status={$r['status']}\n";
    foreach($r['top'] as $i=>$c){
        printf("  %d. %-40s [%s] %.2f family=%s\n",
            $i+1,$c['name'],$c['key'],$c['score'],$c['family']??'-');
    }
}
