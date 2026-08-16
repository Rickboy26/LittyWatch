<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/ParserEngine.php';
if(!is_file($file)){fwrite(STDERR,"ERROR: ParserEngine.php ontbreekt.\n");exit(1);}

$code=file_get_contents($file);
if($code===false){fwrite(STDERR,"ERROR: lezen mislukt.\n");exit(1);}

$marker='LITTYWATCH_PHASE7E5_FIX4_GHOSTLY_STRONGBOX_FINAL_INVARIANT';
if(str_contains($code,$marker)){
    echo "Phase 7E.5 FIX4 staat al geïnstalleerd.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e5-fix4-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/ParserEngine.php');

/*
 * Insert the final invariant immediately before the final parse dedup/return.
 * Support both current common return shapes.
 */
$anchors=[
    '        return $this->deduplicate($this->suppressGenericCatalogShadows($results, $normalized));',
    '        return $this->deduplicate($results);',
];

$found=null;
foreach($anchors as $anchor){
    if(str_contains($code,$anchor)){
        $found=$anchor;
        break;
    }
}

if($found===null){
    // Fallback: locate the last return using deduplicate in parse().
    if(!preg_match('/^(\s*)return\s+\$this->deduplicate\([^;]+;\s*$/mu',$code,$m,PREG_OFFSET_CAPTURE)){
        copy($backup.'/ParserEngine.php',$file);
        fwrite(STDERR,"ERROR: finale deduplicate-return anchor niet gevonden.\n");
        exit(1);
    }
    $found=$m[0][0];
}

$inject=<<<'PHP'
        // LITTYWATCH_PHASE7E5_FIX4_GHOSTLY_STRONGBOX_FINAL_INVARIANT
        // A possessive "Ghostly Hero's Strongbox" is a strongbox identity and
        // must never leak out as the Miniature Ghostly Hero. This is deliberately
        // a final safety invariant because several legacy alias paths can create
        // the miniature before/after semantic normalization.
        if (preg_match('/\bghostly\s+hero[\'’]s\s+strongboxes?\b/iu', $message)) {
            $results = array_values(array_filter(
                $results,
                static function (ParsedOffer $offer): bool {
                    return mb_strtolower(trim($offer->item)) !== 'miniature ghostly hero';
                }
            ));
        }

PHP;

$pos=strrpos($code,$found);
if($pos===false){
    copy($backup.'/ParserEngine.php',$file);
    fwrite(STDERR,"ERROR: return-positie niet gevonden.\n");
    exit(1);
}

$code=substr($code,0,$pos).$inject.substr($code,$pos);

if(file_put_contents($file,$code)===false){
    copy($backup.'/ParserEngine.php',$file);
    fwrite(STDERR,"ERROR: schrijven mislukt; backup teruggezet.\n");
    exit(1);
}

$out=[];$rc=0;
exec('/usr/bin/php -l '.escapeshellarg($file),$out,$rc);
if($rc!==0){
    copy($backup.'/ParserEngine.php',$file);
    fwrite(STDERR,"ERROR: syntaxfout; backup teruggezet.\n".implode("\n",$out)."\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.5 FIX4 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Final invariant: Ghostly Hero's Strongbox kan niet als Miniature Ghostly Hero uitlekken.\n";
echo "Echte Ghostly Hero miniature-advertenties blijven toegestaan.\n";
