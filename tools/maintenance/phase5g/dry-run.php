<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm5g(string $v):string{
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/\bq\s*\d{1,2}\b/iu',' ',$v)??$v;
    $v=preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:a|e|k|plat)\b/iu',' ',$v)??$v;
    $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}

$patterns=[
    // Typos / unique weapons / keys
    ['/\bddroknar key\b/iu',"Droknar's Key"],
    ['/\bemeral blade\b/iu','Emerald Blade'],
    ['/\bzodiaq\b/iu','Zodiac Sword'],
    ['/\bcursus\b/iu','Curses Staff'],
    ['/\baoken aegis\b/iu','Aureate Aegis'],
    ['/\bsharp stick\b/iu','The Sharp Stick'],
    ['/\bmeasure4measure\b/iu','Measure for Measure'],
    ['/\bstilettos\b/iu','Stilettos'],

    // Consumables / materials
    ['/\bfow scrolls?\b/iu','Passage Scroll to the Fissure of Woe'],
    ['/\bobs?hards?\b/iu','Obsidian Shard'],
    ['/\bfirewater\b/iu','Dwarven Ale'],
    ['/\bgrails?\b/iu','Grail of Might'],
    ['/\beggs?\b/iu','Golden Egg'],
    ['/\broyal gifts?\b/iu','Royal Gift'],
    ['/\bwarband supplies\b/iu','War Supplies'],
    ['/\babnormal seeds?\b/iu','Abnormal Seed'],
    ['/\bsuperbcharr carvings?\b/iu','Superb Charr Carving'],

    // Tonics / miniatures with explicit names
    ['/\bel mursaat\b/iu','Everlasting Mursaat Tonic'],
    ['/\bmursaat elementalist polymo\b/iu','Mursaat Elementalist Polymock Piece'],

    // Greens / uniques only when exact catalogue name is expected
    ['/\bbeautiful menzies\b/iu',"Menzies' Sorrow"],
    ['/\bbow of the paragon\b/iu','Bow of the Hierophant'],
];

$find=$db->prepare("SELECT key,name FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");

$groups=$db->query("
SELECT id,item_sample,segment_sample,offer_count
FROM parser_residual_groups
WHERE decision='keep_unresolved'
ORDER BY offer_count DESC,id
")->fetchAll(PDO::FETCH_ASSOC);

$candidates=[];
foreach($groups as $g){
    $text=(string)$g['item_sample'].' '.(string)$g['segment_sample'];

    foreach($patterns as [$pat,$canonical]){
        if(!preg_match($pat,$text))continue;

        $find->execute([$canonical]);
        $row=$find->fetch(PDO::FETCH_ASSOC);
        if(!$row)continue;

        $alias=trim((string)$g['item_sample']);
        $norm=norm5g($alias);
        if($norm===''||mb_strlen(str_replace(' ','',$norm))<4)continue;

        $candidates[]=[
            'group_id'=>(int)$g['id'],
            'alias'=>$alias,
            'normalized_alias'=>$norm,
            'item_key'=>(string)$row['key'],
            'item_name'=>(string)$row['name'],
            'offer_count'=>(int)$g['offer_count'],
            'confidence'=>0.99,
            'pattern'=>$pat
        ];
    }
}

$uniq=[];
foreach($candidates as $c){
    $k=$c['normalized_alias'].'|'.$c['item_key'];
    if(!isset($uniq[$k])||$c['offer_count']>$uniq[$k]['offer_count'])$uniq[$k]=$c;
}
$candidates=array_values($uniq);

$outDir=dirname(__DIR__,3).'/data/exports';
if(!is_dir($outDir))mkdir($outDir,0775,true);
$path=$outDir.'/littywatch-phase5g-alias-dryrun-'.date('Ymd-His').'.json';
file_put_contents($path,json_encode($candidates,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

echo "Phase 5G alias dry-run klaar.\n";
echo "Candidates: ".count($candidates)."\n";
echo "Rapport: {$path}\n\n";
foreach($candidates as $c){
    printf("%-30s -> %-38s [%s] x%d\n",
        $c['alias'],$c['item_name'],$c['item_key'],$c['offer_count']);
}
