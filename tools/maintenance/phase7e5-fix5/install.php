<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/ParserEngine.php';
if(!is_file($file)){fwrite(STDERR,"ERROR: ParserEngine.php ontbreekt.\n");exit(1);}
$code=file_get_contents($file);
if($code===false){fwrite(STDERR,"ERROR: lezen mislukt.\n");exit(1);}

$marker='LITTYWATCH_PHASE7E5_FIX5_STRONGBOX_POST_DEDUP';
if(str_contains($code,$marker)){
    echo "Phase 7E.5 FIX5 staat al geïnstalleerd.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e5-fix5-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/ParserEngine.php');

/* Remove FIX4 block if present. */
$code=preg_replace(
    '~\n\s*// LITTYWATCH_PHASE7E5_FIX4_GHOSTLY_STRONGBOX_FINAL_INVARIANT.*?\n\s*}\n(?=\s*return \$this->deduplicate\()~su',
    "\n",
    $code,
    1
) ?? $code;

$anchor='        return $this->deduplicate($this->suppressGenericCatalogShadows($results, $normalized));';
if(!str_contains($code,$anchor)){
    copy($backup.'/ParserEngine.php',$file);
    fwrite(STDERR,"ERROR: finale parse-return anchor niet gevonden.\n");
    exit(1);
}

$replacement=<<<'PHP'
        // LITTYWATCH_PHASE7E5_FIX5_STRONGBOX_POST_DEDUP
        // Build the actual final result first. Then apply the source/item invariant,
        // so no later parser transformation can re-introduce this false positive.
        $results = $this->deduplicate($this->suppressGenericCatalogShadows($results, $normalized));

        $sourceGuard = preg_match(
            '/\bghostly\s+hero(?:[\'’]s|s)?[^\p{L}\p{N}]{0,4}strong\s*boxes?\b/iu',
            $message
        ) === 1;

        if ($sourceGuard) {
            $results = array_values(array_filter(
                $results,
                static function (ParsedOffer $offer): bool {
                    $item = mb_strtolower(trim($offer->item));
                    $item = preg_replace('/\s+/u', ' ', $item) ?? $item;
                    $key = mb_strtolower(trim($offer->itemKey));

                    $isGhostlyHeroMini =
                        preg_match('/^miniature\s+ghostly\s+hero$/iu', $item) === 1
                        || in_array($key, ['ghostly_hero','ghostly-hero','miniature_ghostly_hero','miniature-ghostly-hero'], true);

                    return !$isGhostlyHeroMini;
                }
            ));
        }

        return $results;
PHP;

$code=str_replace($anchor,$replacement,$code);

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

echo "OK: LittyWatch V5.2 Phase 7E.5 FIX5 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Strongbox invariant draait nu NA suppressGenericCatalogShadows + deduplicate.\n";
echo "Itemcontrole gebruikt zowel canonical naam als bekende Ghostly Hero item keys.\n";
