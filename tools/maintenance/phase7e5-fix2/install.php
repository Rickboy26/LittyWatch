<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$files=[
    $root.'/app/Market/ContextAwareCandidatePipeline.php',
    $root.'/app/Parser/ParserEngine.php',
];

foreach($files as $file){
    if(!is_file($file)){
        fwrite(STDERR,"ERROR: ontbreekt: {$file}\n");
        exit(1);
    }
}

$backup=$root.'/storage/backups/phase7e5-fix2-'.date('Ymd-His');
@mkdir($backup,0775,true);
foreach($files as $file) copy($file,$backup.'/'.basename($file));

/* ============================================================
 * 1. ContextAwareCandidatePipeline
 * Direct single-candidate recovery for "unded/Livia" and ded/Livia.
 * This must happen before normal list expansion, because list expansion
 * intentionally discards results with fewer than two final candidates.
 * ============================================================ */
$file=$files[0];
$code=file_get_contents($file);

if(!str_contains($code,'LITTYWATCH_PHASE7E5_FIX2_LIVIA_SINGLE_RECOVERY')){
    $anchor=<<<'PHP'
        $item=trim((string)($row['item']??''));
        $raw=trim((string)($row['raw_segment']??''));
        $source=$this->sourceFor($item,$raw,$message);
PHP;

    if(!str_contains($code,$anchor)){
        fwrite(STDERR,"ERROR: ContextAwareCandidatePipeline expand-anchor niet gevonden.\n");
        exit(1);
    }

    $replacement=<<<'PHP'
        $item=trim((string)($row['item']??''));
        $raw=trim((string)($row['raw_segment']??''));

        // LITTYWATCH_PHASE7E5_FIX2_LIVIA_SINGLE_RECOVERY
        // "unded/Livia" is one concrete miniature offer. The generic list
        // pipeline rejects single-result expansions by design, so recover it here.
        foreach ([$item,$raw] as $liviaSource) {
            if (preg_match('/^\s*(unded(?:icated)?|ded(?:icated)?)\s*\/\s*livia(?:\s+.*)?$/iu', $liviaSource, $m)) {
                $state = str_starts_with(mb_strtolower((string)$m[1]), 'unded') ? 'unded' : 'ded';
                return [[
                    'item'=>'Miniature Livia '.$state,
                    'raw_segment'=>'Miniature Livia '.$state,
                ]];
            }
        }

        $source=$this->sourceFor($item,$raw,$message);
PHP;

    $code=str_replace($anchor,$replacement,$code);
}

file_put_contents($file,$code);


/* ============================================================
 * 2. ParserEngine
 * The dedication-restoration alias table scans the original source.
 * Exclude "ghostly hero's strongbox" from Ghostly Hero miniature aliases.
 * ============================================================ */
$file=$files[1];
$code=file_get_contents($file);

if(!str_contains($code,'LITTYWATCH_PHASE7E5_FIX2_GHOSTLY_STRONGBOX_SOURCE_GUARD')){
    $old1="'ghostly_hero' => ['ghostly\\s+hero', 'ghero'],";
    $old2="'miniature_ghostly_hero' => ['ghostly\\s+hero', 'ghero'],";

    if(!str_contains($code,$old1) || !str_contains($code,$old2)){
        fwrite(STDERR,"ERROR: ParserEngine Ghostly Hero alias anchors niet gevonden.\n");
        exit(1);
    }

    $new1="// LITTYWATCH_PHASE7E5_FIX2_GHOSTLY_STRONGBOX_SOURCE_GUARD\n"
        ."                'ghostly_hero' => ['ghostly\\s+hero(?![\\'’]s?\\s+strongbox)', 'ghero'],";
    $new2="'miniature_ghostly_hero' => ['ghostly\\s+hero(?![\\'’]s?\\s+strongbox)', 'ghero'],";

    $code=str_replace($old1,$new1,$code);
    $code=str_replace($old2,$new2,$code);
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
        fwrite(STDERR,"ERROR: syntaxfout; FIX2 teruggedraaid.\n".implode("\n",$out)."\n");
        exit(1);
    }
}

echo "OK: LittyWatch V5.2 Phase 7E.5 FIX2 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Fixes:\n";
echo "  - unded/Livia wordt vóór list-count safety als single Miniature Livia candidate hersteld\n";
echo "  - ParserEngine Ghostly Hero source-alias sluit possessive strongbox context uit\n";
echo "  - bestaande stacks-of-alc FIX1 blijft ongewijzigd\n";
