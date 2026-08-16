<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/ParserEngine.php';

if(!is_file($file)){fwrite(STDERR,"ERROR: ParserEngine.php ontbreekt.\n");exit(1);}
$code=file_get_contents($file);
if($code===false){fwrite(STDERR,"ERROR: ParserEngine.php lezen mislukt.\n");exit(1);}

$marker='LITTYWATCH_PHASE7E5_FIX3_PARSER_PRENORMALIZATION';
if(str_contains($code,$marker)){
    echo "Phase 7E.5 FIX3 staat al geïnstalleerd.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e5-fix3-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/ParserEngine.php');

/*
 * Patch directly after base Normalizer, before MessageGate/classification/splitting.
 * This ensures both cases are fixed before any semantic alias or grammar stage.
 */
$anchor=<<<'PHP'
        $normalized = $this->normalizer->normalize($message);
        $gate = $this->messageGate->inspect($normalized);
PHP;

if(!str_contains($code,$anchor)){
    fwrite(STDERR,"ERROR: parse() normalization anchor niet gevonden.\n");
    exit(1);
}

$replacement=<<<'PHP'
        $normalized = $this->normalizer->normalize($message);

        // LITTYWATCH_PHASE7E5_FIX3_PARSER_PRENORMALIZATION
        // Compact state/name slash shorthand is one explicit miniature offer.
        // Canonicalize before segmentation so "unded/Livia" is not split into
        // a generic state fragment plus a bare name.
        $normalized = preg_replace_callback(
            '/\b(unded(?:icated)?|ded(?:icated)?)\s*\/\s*livia\b/iu',
            static function(array $m): string {
                $state = str_starts_with(mb_strtolower((string)$m[1]), 'unded') ? 'unded' : 'ded';
                return 'Miniature Livia ' . $state;
            },
            $normalized
        ) ?? $normalized;

        // "Ghostly Hero's Strongbox" is strongbox market context, never the
        // Miniature Ghostly Hero. Protect it before classifier/semantic passes.
        $normalized = preg_replace(
            '/\bGhostly\s+Hero[\'’]s\s+Strongboxes?\b/iu',
            "Hero's Strongbox",
            $normalized
        ) ?? $normalized;

        $gate = $this->messageGate->inspect($normalized);
PHP;

$code=str_replace($anchor,$replacement,$code);

if(file_put_contents($file,$code)===false){
    copy($backup.'/ParserEngine.php',$file);
    fwrite(STDERR,"ERROR: schrijven mislukt; backup teruggezet.\n");
    exit(1);
}

exec('/usr/bin/php -l '.escapeshellarg($file),$out,$rc);
if($rc!==0){
    copy($backup.'/ParserEngine.php',$file);
    fwrite(STDERR,"ERROR: syntaxfout; backup teruggezet.\n".implode("\n",$out)."\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.5 FIX3 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Parser pre-normalization actief voor:\n";
echo "  - unded/Livia en ded/Livia\n";
echo "  - Ghostly Hero's Strongbox => Hero's Strongbox\n";
echo "Bestaande alcohol-fix blijft ongewijzigd.\n";
