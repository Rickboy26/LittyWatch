<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm6b(string $v): string {
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function attr6b(string $text): ?string {
    $map=[
        'domination magic'=>['domination','dom'],
        'illusion magic'=>['illusion','illus','illu'],
        'curses'=>['curses','curs'],
        'spawning power'=>['spawning power','spawning','spaw'],
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
    $n=' '.norm6b($text).' ';
    foreach($map as $canonical=>$aliases){
        foreach($aliases as $a){
            if(preg_match('/(?:^|\s)'.preg_quote($a,'/').'(?:\s|$)/iu',$n))return $canonical;
        }
    }
    return null;
}
function family6b(string $text): ?string {
    $n=norm6b($text);
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
function skin6b(string $item): string {
    $n=norm6b($item);
    $drop=[
        'dom','domination','illusion','illus','illu','curs','curses','spaw','spawning',
        'comm','communing','prot','protection','heal','healing','smite','smiting',
        'sr','death','blood','fire','water','air','earth','df','divine','fc','es',
        'q9','q10','q11','q12','q13'
    ];
    $t=array_values(array_filter(explode(' ',$n),fn($x)=>!in_array($x,$drop,true)));
    return trim(implode(' ',$t));
}
function nameFamily6b(string $name): ?string {
    return family6b($name);
}
function scoreSkin6b(string $skin,string $candidate): float {
    $s=norm6b($skin);$c=norm6b($candidate);
    if($s===''||$c==='')return 0.0;
    if(str_contains($c,$s))return 1.0;
    similar_text($s,$c,$pct);
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

    $combined=(string)$g['item_sample'].' '.(string)$g['segment_sample'];
    foreach($examples as $e){
        $combined.=' '.(string)($e['raw_segment']??'').' '.(string)($e['raw_message']??'');
    }

    $attribute=attr6b((string)$g['item_sample'].' '.(string)$g['segment_sample']);
    if($attribute===null)$attribute=attr6b($combined);

    // Prefer family from group clause. Fall back to raw messages only if the
    // same family occurs repeatedly.
    $family=family6b((string)$g['segment_sample']);
    if($family===null){
        $counts=[];
        foreach($examples as $e){
            $f=family6b((string)($e['raw_segment']??'').' '.(string)($e['raw_message']??''));
            if($f)$counts[$f]=($counts[$f]??0)+1;
        }
        arsort($counts);
        $topFamily=array_key_first($counts);
        if($topFamily!==null && ($counts[$topFamily]??0)>=max(2,(int)ceil(count($examples)*0.5))){
            $family=$topFamily;
        }
    }

    $skin=skin6b((string)$g['item_sample']);
    $cands=[];

    foreach($catalog as $c){
        $name=(string)$c['name'];
        $candFamily=nameFamily6b($name);

        if($family!==null && $candFamily!==$family)continue;

        $skinScore=scoreSkin6b($skin,$name);
        if($skinScore<0.58)continue;

        // Attribute evidence is contextual, because catalogue names often
        // don't encode caster attributes. We report it, but don't fabricate
        // compatibility when the catalogue has no explicit profile.
        $score=$skinScore;
        if($family!==null)$score+=0.08;
        if($attribute!==null)$score+=0.04;
        $score=min(1.0,$score);

        $cands[]=[
            'key'=>(string)$c['key'],
            'name'=>$name,
            'score'=>round($score,4),
            'family'=>$candFamily,
        ];
    }

    usort($cands,fn($a,$b)=>$b['score']<=>$a['score']);
    $top=array_slice($cands,0,8);

    $status='none';
    if($top){
        $margin=count($top)>1?$top[0]['score']-$top[1]['score']:1.0;
        if($family!==null && $attribute!==null && $top[0]['score']>=0.96 && $margin>=0.10){
            $status='strong_context';
        } elseif($top[0]['score']>=0.82 && $margin>=0.08){
            $status='review';
        } else {
            $status='ambiguous';
        }
    }

    $results[]=[
        'group_id'=>(int)$g['id'],
        'item_sample'=>(string)$g['item_sample'],
        'segment_sample'=>(string)$g['segment_sample'],
        'offer_count'=>(int)$g['offer_count'],
        'skin'=>$skin,
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

echo "Phase 6B context-aware green dry-run klaar.\n";
echo "Groups analysed: ".count($results)."\n";
echo "Rapport: {$path}\n\n";

foreach($results as $r){
    echo "#{$r['group_id']} {$r['item_sample']} x{$r['offer_count']}\n";
    echo "  skin={$r['skin']} attribute=".($r['attribute']??'-')." family=".($r['family']??'-')." status={$r['status']}\n";
    foreach($r['top'] as $i=>$c){
        printf("  %d. %-40s [%s] %.2f family=%s\n",
            $i+1,$c['name'],$c['key'],$c['score'],$c['family']??'-');
    }
    if($r['examples']){
        echo "  examples:\n";
        foreach($r['examples'] as $e){
            echo "    - ".preg_replace('/\s+/u',' ',trim((string)$e['raw_message']))."\n";
        }
    }
}
