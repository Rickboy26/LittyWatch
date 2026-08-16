<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$files=[
    $root.'/app/Parser/MarketBundleExpander.php',
    $root.'/app/Market/ContextAwareCandidatePipeline.php',
    $root.'/app/Parser/SemanticNormalizer.php',
];

foreach($files as $file){
    if(!is_file($file)){
        fwrite(STDERR,"ERROR: ontbreekt: {$file}\n");
        exit(1);
    }
}

$backup=$root.'/storage/backups/phase7e5-fix1-'.date('Ymd-His');
@mkdir($backup,0775,true);
foreach($files as $file) copy($file,$backup.'/'.basename($file));

/* =============================================================
 * 1. MarketBundleExpander
 * Remove broken FIX0 code from expandRepeatedPointList and handle
 * stacks-of-alc in the main expansion path, returning list<string>.
 * ============================================================= */
$file=$files[0];
$code=file_get_contents($file);

$broken=<<<'PHP'
        // LITTYWATCH_PHASE7E5_ALC_STACKS
        if(preg_match('/\b(?:(\d+)\s+)?stacks?\s+of\s+alc(?:ohol)?\b/iu',$text,$m)){
            $stacks=isset($m[1])&&$m[1]!==''?max(1,(int)$m[1]):1;
            return [['text'=>'Alcohol Points','item'=>'Alcohol Points','quantity'=>$stacks*250]];
        }

PHP;
$code=str_replace($broken,'',$code);

if(!str_contains($code,'LITTYWATCH_PHASE7E5_FIX1_ALC_STACKS')){
    $anchor=<<<'PHP'
        if ($doa !== null) return $doa;

        return null;
PHP;
    if(!str_contains($code,$anchor)){
        fwrite(STDERR,"ERROR: MarketBundleExpander main-return anchor niet gevonden.\n");
        exit(1);
    }

    $replacement=<<<'PHP'
        if ($doa !== null) return $doa;

        // LITTYWATCH_PHASE7E5_FIX1_ALC_STACKS
        // Kamadan shorthand: one stack of alcohol consumables represents
        // 250 drunkard/alcohol title points.
        if (preg_match('/\b(?:(\d+)\s+)?stacks?\s+of\s+alc(?:ohol)?\b/iu', $text, $m)) {
            $stacks = isset($m[1]) && $m[1] !== '' ? max(1, (int)$m[1]) : 1;
            return [($stacks * 250) . ' Alcohol Points'];
        }

        return null;
PHP;
    $code=str_replace($anchor,$replacement,$code);
}

file_put_contents($file,$code);


/* =============================================================
 * 2. ContextAwareCandidatePipeline
 * Remove broken $text block and normalize compact state/name slash
 * before normal miniature inheritance.
 * ============================================================= */
$file=$files[1];
$code=file_get_contents($file);

$broken=<<<'PHP'
        // LITTYWATCH_PHASE7E5_UNDED_SLASH_LIVIA
        if(preg_match('/^\s*(unded(?:icated)?|ded(?:icated)?)\s*\/\s*livia\s*$/iu',$text,$m)){
            $state=str_starts_with(mb_strtolower((string)$m[1]),'unded')?'unded':'ded';
            return [['candidate'=>'Miniature Livia','context'=>$state.' Miniature Livia','source'=>$text]];
        }
PHP;
$code=str_replace($broken,'',$code);

if(!str_contains($code,'LITTYWATCH_PHASE7E5_FIX1_UNDED_SLASH_LIVIA')){
    $anchor=<<<'PHP'
    private function inheritMiniatureContext(array $parts,string $source): array
    {
        $explicitMini=preg_match('/\bmini(?:ature)?s?\b/iu',$source)===1;
PHP;
    if(!str_contains($code,$anchor)){
        fwrite(STDERR,"ERROR: inheritMiniatureContext anchor niet gevonden.\n");
        exit(1);
    }

    $replacement=<<<'PHP'
    private function inheritMiniatureContext(array $parts,string $source): array
    {
        // LITTYWATCH_PHASE7E5_FIX1_UNDED_SLASH_LIVIA
        // A compact "unded/Livia" is one miniature offer, not a generic header
        // followed by an unrelated token.
        if (preg_match('/^\s*(unded(?:icated)?|ded(?:icated)?)\s*\/\s*livia\s*$/iu', $source, $m)) {
            $state = str_starts_with(mb_strtolower((string)$m[1]), 'unded') ? 'unded' : 'ded';
            return ['Miniature Livia ' . $state];
        }

        $explicitMini=preg_match('/\bmini(?:ature)?s?\b/iu',$source)===1;
PHP;
    $code=str_replace($anchor,$replacement,$code);
}

file_put_contents($file,$code);


/* =============================================================
 * 3. SemanticNormalizer
 * Move Ghostly Hero's Strongbox canonicalization BEFORE miniature
 * alias expansion. Keep negative lookahead as second safety layer.
 * ============================================================= */
$file=$files[2];
$code=file_get_contents($file);

$late=<<<'PHP'
        // LITTYWATCH_PHASE7E5_GHOSTLY_HERO_STRONGBOX_CANONICAL
        $text=preg_replace('/\bGhostly\s+Hero[\'’]s\s+Strongboxes?\b/iu',"Hero's Strongbox",$text)??$text;
PHP;
$code=str_replace($late,'',$code);

if(!str_contains($code,'LITTYWATCH_PHASE7E5_FIX1_GHOSTLY_STRONGBOX_EARLY')){
    $anchor=<<<'PHP'
        // Targeted miniature names that are unsafe as bare aliases because NPCs
PHP;
    $pos=strpos($code,$anchor);
    if($pos===false){
        fwrite(STDERR,"ERROR: SemanticNormalizer miniature-alias anchor niet gevonden.\n");
        exit(1);
    }

    $early=<<<'PHP'
        // LITTYWATCH_PHASE7E5_FIX1_GHOSTLY_STRONGBOX_EARLY
        // Protect strongbox identity before any bare Ghostly Hero miniature alias.
        $text = preg_replace(
            '/\bGhostly\s+Hero[\'’]s\s+Strongboxes?\b/iu',
            "Hero's Strongbox",
            $text
        ) ?? $text;

PHP;
    $code=substr($code,0,$pos).$early.substr($code,$pos);
}

file_put_contents($file,$code);


/* lint + rollback */
foreach($files as $file){
    $out=[];$rc=0;
    exec('/usr/bin/php -l '.escapeshellarg($file),$out,$rc);
    if($rc!==0){
        foreach($files as $restore){
            $b=$backup.'/'.basename($restore);
            if(is_file($b)) copy($b,$restore);
        }
        fwrite(STDERR,"ERROR: syntaxfout; FIX1 teruggedraaid.\n".implode("\n",$out)."\n");
        exit(1);
    }
}

echo "OK: LittyWatch V5.2 Phase 7E.5 FIX1 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Correcties:\n";
echo "  - stacks of alc draait nu in bereikbaar main-expansion pad en geeft strings terug\n";
echo "  - unded/Livia gebruikt source + list<string> in inheritMiniatureContext\n";
echo "  - Ghostly Hero's Strongbox wordt vóór miniature aliases naar Hero's Strongbox genormaliseerd\n";
