<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function normg(string $v):string{
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function tokensg(string $v):array{
    return array_values(array_unique(array_filter(explode(' ',normg($v)),fn($t)=>mb_strlen($t)>=2)));
}
function scoreg(string $q,string $c):float{
    $qn=normg($q);$cn=normg($c);
    if($qn===''||$cn==='')return 0.0;
    if($qn===$cn)return 1.0;
    $qt=tokensg($qn);$ct=tokensg($cn);
    $inter=count(array_intersect($qt,$ct));
    if($inter===0)return 0.0;
    $union=count(array_unique(array_merge($qt,$ct)));
    $jac=$union?($inter/$union):0.0;
    similar_text($qn,$cn,$pct);
    return min(1.0,max($jac,($pct/100)*0.9));
}

$targets=['Bo Dom Curs','Ghost Spaw','Outcast Dom','Plag Illus','Jade Sp','Demrikov','Primeval Remna','Curse of Thul Za','Beautiful Menzies','Japa'];

$catalog=[];
foreach($db->query("SELECT key,name FROM kb_items WHERE active=1") as $r){
    $catalog[]=['key'=>(string)$r['key'],'name'=>(string)$r['name']];
}

$findGroup=$db->prepare("
SELECT id,item_sample,segment_sample,offer_count
FROM parser_residual_groups
WHERE decision='keep_unresolved' AND lower(trim(item_sample))=lower(trim(?))
ORDER BY offer_count DESC LIMIT 1");

$rows=[];
foreach($targets as $target){
    $findGroup->execute([$target]);
    $g=$findGroup->fetch(PDO::FETCH_ASSOC);
    if(!$g)continue;

    $scores=[];
    foreach($catalog as $c){
        $s=scoreg($target,$c['name']);
        if($s>=0.55)$scores[]=['key'=>$c['key'],'name'=>$c['name'],'score'=>round($s,4)];
    }
    usort($scores,fn($a,$b)=>$b['score']<=>$a['score']);
    $rows[]=[
        'group_id'=>(int)$g['id'],
        'item_sample'=>(string)$g['item_sample'],
        'offer_count'=>(int)$g['offer_count'],
        'top'=>array_slice($scores,0,5),
    ];
}

$outDir=dirname(__DIR__,3).'/data/exports';
if(!is_dir($outDir))mkdir($outDir,0775,true);
$path=$outDir.'/littywatch-phase5i-greens-dryrun-'.date('Ymd-His').'.json';
file_put_contents($path,json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

echo "=== GREEN/UNIQUE DRY-RUN ONLY ===\n";
echo "Rapport: {$path}\n\n";
foreach($rows as $r){
    echo "#{$r['group_id']} {$r['item_sample']} x{$r['offer_count']}\n";
    foreach($r['top'] as $i=>$c){
        printf("  %d. %-40s [%s] %.2f\n",$i+1,$c['name'],$c['key'],$c['score']);
    }
}
