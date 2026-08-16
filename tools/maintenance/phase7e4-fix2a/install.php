<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/ParserEngine.php';

if(!is_file($file)){fwrite(STDERR,"ERROR: ParserEngine.php ontbreekt.\n");exit(1);}
$code=file_get_contents($file);
if($code===false){fwrite(STDERR,"ERROR: lezen mislukt.\n");exit(1);}

$marker='LITTYWATCH_PHASE7E4_FIX2A_MARGO_CONFIDENCE';
if(str_contains($code,$marker)){
    echo "Phase 7E.4 FIX2a staat al in ParserEngine.php.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e4-fix2a-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/ParserEngine.php');

/*
 * Hook into the trusted-catalog promotion pass, where low-confidence
 * catalog matches are already selectively promoted.
 */
$anchor="            if (\$item === 'voltaic spear' && preg_match('/\\b(?:volta|voltaic spear)\\b/iu', \$segment.' '.\$fullMessage)) {";
$pos=strpos($code,$anchor);
if($pos===false){
    copy($backup.'/ParserEngine.php',$file);
    fwrite(STDERR,"ERROR: promotePhase2RTrustedCatalogMatches anchor niet gevonden.\n");
    exit(1);
}

$inject=<<<'PHP'
            // LITTYWATCH_PHASE7E4_FIX2A_MARGO_CONFIDENCE
            // Standalone Margo/Margos is established Kamadan shorthand for
            // Margonite Gemstone. Do not match "El margo" (tonic shorthand).
            if ($item === 'margonite gemstone'
                && preg_match('/(?:^|[\s|,;])margos?(?:$|[\s|,;0-9])/iu', $segment.' '.$fullMessage)
                && !preg_match('/\bel\s+margo\b/iu', $segment.' '.$fullMessage)) {
                return new ParsedOffer(
                    $offer->tradeType, $offer->item, $offer->itemKey, $offer->modifiers, $offer->price,
                    max(0.90, $offer->confidence), 'accepted', 'catalog_match', $offer->segment,
                    $offer->tokens, $offer->profile, $offer->relevantProperties, $offer->marketKey, $offer->exchange
                );
            }

PHP;

$code=substr($code,0,$pos).$inject.substr($code,$pos);

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

echo "OK: LittyWatch V5.2 Phase 7E.4 FIX2a geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Standalone Margo/Margos => Margonite Gemstone wordt trusted catalog_match.\n";
echo "El margo blijft gereserveerd voor Everlasting Margonite Tonic.\n";
