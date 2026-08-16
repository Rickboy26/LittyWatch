<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/ParserEngine.php';

if(!is_file($file)){
    fwrite(STDERR,"ERROR: ParserEngine.php ontbreekt.\n");
    exit(1);
}

$code=file_get_contents($file);
if($code===false){
    fwrite(STDERR,"ERROR: ParserEngine.php lezen mislukt.\n");
    exit(1);
}

$marker='LITTYWATCH_PHASE7E5_FIX6_STRONGBOX_SOURCE_GUARD';
if(str_contains($code,$marker)){
    echo "Phase 7E.5 FIX6 staat al geïnstalleerd.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e5-fix6-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/ParserEngine.php');

$old=<<<'PHP'
        $sourceGuard = preg_match(
            '/\bghostly\s+hero(?:[\'’]s|s)?[^\p{L}\p{N}]{0,4}strong\s*boxes?\b/iu',
            $message
        ) === 1;
PHP;

if(!str_contains($code,$old)){
    fwrite(STDERR,"ERROR: FIX5 sourceGuard anchor niet gevonden.\n");
    exit(1);
}

$new=<<<'PHP'
        // LITTYWATCH_PHASE7E5_FIX6_STRONGBOX_SOURCE_GUARD
        // Match actual Kamadan forms:
        //   Ghostly Hero's Strongbox
        //   Ghostly Heros Strongbox
        //   Ghostly Hero Strongbox
        // Apostrophe/possessive handling is deliberately separated from whitespace.
        $sourceGuard = preg_match(
            '/\bghostly\s+hero(?:[\'’]s|s)?\s+strong\s*boxes?\b/iu',
            $message
        ) === 1;
PHP;

$code=str_replace($old,$new,$code);

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

echo "OK: LittyWatch V5.2 Phase 7E.5 FIX6 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Alleen sourceGuard-regex aangepast.\n";
echo "FIX5 post-dedup invariant en item-key detectie blijven ongewijzigd.\n";
