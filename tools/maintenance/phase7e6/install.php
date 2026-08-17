<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/MarketBundleExpander.php';

if(!is_file($file)){
    fwrite(STDERR,"ERROR: MarketBundleExpander.php ontbreekt.\n");
    exit(1);
}

$code=file_get_contents($file);
if($code===false){
    fwrite(STDERR,"ERROR: MarketBundleExpander.php lezen mislukt.\n");
    exit(1);
}

$marker='LITTYWATCH_PHASE7E6_MINIATURE_SLASH_LIST_STATE';
if(str_contains($code,$marker)){
    echo "Phase 7E.6 staat al geïnstalleerd.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e6-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/MarketBundleExpander.php');

$anchor="        'zhed shadowhoof'=>'Miniature Zhed Shadowhoof',";
if(!str_contains($code,$anchor)){
    fwrite(STDERR,"ERROR: MINIATURES anchor niet gevonden.\n");
    exit(1);
}

$insert=<<<'PHP'
        'zhed shadowhoof'=>'Miniature Zhed Shadowhoof',
        // LITTYWATCH_PHASE7E6_MINIATURE_SLASH_LIST_STATE
        // Explicit ded/unded before a slash/comma list is inherited by every
        // concrete miniature member. Add the missing canonical list aliases.
        'livia'=>'Miniature Livia',
        'princess salma'=>'Miniature Princess Salma',
        'salma'=>'Miniature Princess Salma',
PHP;

$code=str_replace($anchor,$insert,$code);

if(file_put_contents($file,$code)===false){
    copy($backup.'/MarketBundleExpander.php',$file);
    fwrite(STDERR,"ERROR: schrijven mislukt; backup teruggezet.\n");
    exit(1);
}

$out=[];$rc=0;
exec('/usr/bin/php -l '.escapeshellarg($file),$out,$rc);
if($rc!==0){
    copy($backup.'/MarketBundleExpander.php',$file);
    fwrite(STDERR,"ERROR: syntaxfout; backup teruggezet.\n".implode("\n",$out)."\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.6 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Miniature slash-list mappings toegevoegd:\n";
echo "  - Livia => Miniature Livia\n";
echo "  - Princess Salma / Salma => Miniature Princess Salma\n";
echo "Bestaande shared ded/unded state propagation blijft leidend.\n";
