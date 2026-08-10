<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function norm5e(string $v):string{
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu',' ',$v)??$v;
    $v=preg_replace('/\bq\s*\d{1,2}\b/iu',' ',$v)??$v;
    $v=preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:a|e|k|plat|arm(?:brace)?s?)\b/iu',' ',$v)??$v;
    $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}

function cleanAlias5e(string $item,string $segment):string{
    $raw=trim($item);
    if($raw==='')$raw=trim($segment);
    $raw=preg_replace('/\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu',' ',$raw)??$raw;
    $raw=preg_replace('/\bq\s*\d{1,2}\b/iu',' ',$raw)??$raw;
    $raw=preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:a|e|k|plat|arm(?:brace)?s?)\b/iu',' ',$raw)??$raw;
    $raw=preg_replace('/\s+/u',' ',$raw)??$raw;
    return trim($raw," \t\n\r\0\x0B,;:/|[]()");
}

$candidates=[];

// Source 1: existing explicit correct_item decisions from 5B/5C/5D.
$q=$db->query("
SELECT id,item_sample,segment_sample,corrected_item,corrected_key,offer_count,notes
FROM parser_residual_groups
WHERE decision='correct_item'
  AND corrected_key IS NOT NULL
  AND corrected_item IS NOT NULL
ORDER BY offer_count DESC,id");

foreach($q as $g){
    $alias=cleanAlias5e((string)$g['item_sample'],(string)$g['segment_sample']);
    $norm=norm5e($alias);
    if($norm==='' || mb_strlen(str_replace(' ','',$norm))<4)continue;

    $canonicalNorm=norm5e((string)$g['corrected_item']);
    if($norm===$canonicalNorm)continue;

    // LITTYWATCH_PHASE5E_FIX1_SKIP_CANONICAL_PUNCTUATION
    // Canonical spelling/punctuation corrections are not reusable market aliases.
    $punctuationInsensitiveAlias = preg_replace('/[^a-z0-9]+/iu','',$norm) ?? $norm;
    $punctuationInsensitiveCanonical = preg_replace('/[^a-z0-9]+/iu','',$canonicalNorm) ?? $canonicalNorm;
    if($punctuationInsensitiveAlias === $punctuationInsensitiveCanonical)continue;
    if($norm === 'not in the face' && $canonicalNorm === 'not the face')continue;

    $candidates[]=[
        'alias'=>$alias,
        'normalized_alias'=>$norm,
        'item_key'=>(string)$g['corrected_key'],
        'item_name'=>(string)$g['corrected_item'],
        'source'=>'reviewed_correct_item',
        'source_group_id'=>(int)$g['id'],
        'confidence'=>1.0,
        'offer_count'=>(int)$g['offer_count'],
        'notes'=>(string)($g['notes']??''),
    ];
}

// Source 2: highly obvious market shorthand among remaining unresolved.
$patterns = [
    // shorthand => canonical name fragment/key lookup hint
    ['/\bghero\b/iu','Miniature Ghostly Hero'],
    ['/\bfow scrolls?\b/iu','Passage Scroll to the Fissure of Woe'],
    ['/\bglad strongboxes?\b/iu',"Gladiator's Zaishen Strongbox"],
    ['/\bnico gift\b/iu',"Gift of the Traveler"],
    ['/\bblack dy\b/iu','Black Dye'],
    ['/\bsilver dyes?\b/iu','Silver Dye'],
    ['/\bminiature shiro\'?ken assassin\b/iu',"Miniature Shiro'ken Assassin"],
    ['/\bshiroken assassin mini\b/iu',"Miniature Shiro'ken Assassin"],
    ['/\bel m\.?o\.?x\.? tonic\b/iu','Everlasting M.O.X. Tonic'],
];

$findName=$db->prepare("SELECT key,name FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");
$groups=$db->query("
SELECT id,item_sample,segment_sample,offer_count
FROM parser_residual_groups
WHERE decision='keep_unresolved'
ORDER BY offer_count DESC,id")->fetchAll(PDO::FETCH_ASSOC);

foreach($groups as $g){
    $text=(string)$g['item_sample'].' '.(string)$g['segment_sample'];
    foreach($patterns as [$pat,$canonical]){
        if(!preg_match($pat,$text))continue;
        $findName->execute([$canonical]);
        $row=$findName->fetch(PDO::FETCH_ASSOC);
        if(!$row)continue;

        $alias=cleanAlias5e((string)$g['item_sample'],(string)$g['segment_sample']);
        $norm=norm5e($alias);
        if($norm==='' || mb_strlen(str_replace(' ','',$norm))<4)continue;

        // Miniatures still require explicit state in source segment.
        if(str_starts_with(mb_strtolower($canonical),'miniature ')
           && !preg_match('/\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu',(string)$g['segment_sample'])){
            continue;
        }

        $candidates[]=[
            'alias'=>$alias,
            'normalized_alias'=>$norm,
            'item_key'=>(string)$row['key'],
            'item_name'=>(string)$row['name'],
            'source'=>'trusted_market_pattern',
            'source_group_id'=>(int)$g['id'],
            'confidence'=>0.99,
            'offer_count'=>(int)$g['offer_count'],
            'notes'=>'Phase 5E trusted shorthand pattern',
        ];
    }
}

// Deduplicate by normalized_alias + key.
$uniq=[];
foreach($candidates as $c){
    $k=$c['normalized_alias'].'|'.$c['item_key'];
    if(!isset($uniq[$k]) || $c['offer_count']>$uniq[$k]['offer_count'])$uniq[$k]=$c;
}
$candidates=array_values($uniq);

$outDir=dirname(__DIR__,3).'/data/exports';
if(!is_dir($outDir))mkdir($outDir,0775,true);
$path=$outDir.'/littywatch-phase5e-alias-dryrun-'.date('Ymd-His').'.json';
file_put_contents($path,json_encode($candidates,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

echo "Phase 5E alias dry-run klaar.\n";
echo "Candidates: ".count($candidates)."\n";
echo "Rapport: {$path}\n\n";

foreach(array_slice($candidates,0,100) as $c){
    printf("%-28s -> %-35s [%s] conf=%.2f x%d source=%s\n",
        $c['alias'],$c['item_name'],$c['item_key'],$c['confidence'],$c['offer_count'],$c['source']);
}
