<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/ParserEngine.php';

if(!is_file($file)){fwrite(STDERR,"ERROR: ParserEngine.php ontbreekt.\n");exit(1);}
$code=file_get_contents($file);
if($code===false){fwrite(STDERR,"ERROR: lezen mislukt.\n");exit(1);}

$marker='LITTYWATCH_PHASE7E5_FIX7_STRONGBOX_REGEX';
if(str_contains($code,$marker)){
    echo "Phase 7E.5 FIX7 staat al geïnstalleerd.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e5-fix7-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/ParserEngine.php');

$old="'/\\bghostly\\s+hero(?:[\\'’]s|s)?\\s+strong\\s*boxes?\\b/iu'";
if(!str_contains($code,$old)){
    fwrite(STDERR,"ERROR: FIX6 regex-anchor niet gevonden.\n");
    exit(1);
}

$new="// LITTYWATCH_PHASE7E5_FIX7_STRONGBOX_REGEX\n            '/\\bghostly\\s+hero(?:[\\'’]s|s)?\\s+strong\\s*box(?:es)?\\b/iu'";

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

echo "OK: LittyWatch V5.2 Phase 7E.5 FIX7 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Regex gecorrigeerd: boxes? -> box(?:es)?\n";
