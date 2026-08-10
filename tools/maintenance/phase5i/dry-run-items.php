<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm5i(string $v):string{
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/\bq\s*\d{1,2}\b/iu',' ',$v)??$v;
    $v=preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:a|e|k|plat)\b/iu',' ',$v)??$v;
    $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}

$patterns=[
    ['/\\bfow scrolls?\\b/iu','Passage Scroll to the Fissure of Woe'],
    ['/\\bsharp stick\\b/iu','The Sharp Stick'],
    ['/\\bstilettos\\b/iu','Stilettos'],
    ['/\\bcursus\\b/iu','Curses Staff'],
    ['/\\bwhite dye\\b/iu','White Dye'],
    ['/\\bsilver dyes?\\b/iu','Silver Dye'],
    ['/\\bz\\s*keys?\\b/iu','Zaishen Key'],
    ['/\\bzcoins?\\b/iu','Copper Zaishen Coin'],
    ['/\\bgzcoin\\b/iu','Gold Zaishen Coin'],
    ['/\\bmy 5x copper zaishen coin\\b/iu','Copper Zaishen Coin'],
    ['/\\bflames? of balth\\b/iu','Flame of Balthazar'],
    ['/\\bel mursaat\\b/iu','Everlasting Mursaat Tonic'],
    ['/\\bel trapdoor\\b/iu','Everlasting Trapdoor Tonic'],
    ['/\\bel slightly mad king\\b/iu','Everlasting Slightly Mad King Thorn Tonic'],
    ['/\\bids\\b/iu','Icy Dragon Sword'],
    ['/\\bobsidians frag\\b/iu','Obsidian Shard'],
    // FIX1: bare "Shards" intentionally removed; too ambiguous.
    ['/\\bplague idols?\\b/iu','Plague Idol'],
    ['/\\broyal gifts?\\b/iu','Royal Gift'],
    ['/\\babnormal seeds?\\b/iu','Abnormal Seed'],
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
        $norm=norm5i($alias);
        if($norm===''||mb_strlen(str_replace(' ','',$norm))<4)continue;

        $candidates[]=[
            'group_id'=>(int)$g['id'],
            'alias'=>$alias,
            'normalized_alias'=>$norm,
            'item_key'=>(string)$row['key'],
            'item_name'=>(string)$row['name'],
            'offer_count'=>(int)$g['offer_count'],
            'confidence'=>0.99
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
$path=$outDir.'/littywatch-phase5i-items-dryrun-'.date('Ymd-His').'.json';
file_put_contents($path,json_encode($candidates,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

echo "Phase 5I FIX1 item dry-run klaar.\n";
echo "Candidates: ".count($candidates)."\n";
echo "Rapport: {$path}\n\n";
foreach($candidates as $c){
    printf("%-30s -> %-40s [%s] x%d\n",
        $c['alias'],$c['item_name'],$c['item_key'],$c['offer_count']);
}
