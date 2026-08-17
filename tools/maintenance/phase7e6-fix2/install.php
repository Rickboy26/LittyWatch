<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/MarketBundleExpander.php';

if(!is_file($file)){fwrite(STDERR,"ERROR: MarketBundleExpander.php ontbreekt.\n");exit(1);}
$code=file_get_contents($file);
if($code===false){fwrite(STDERR,"ERROR: lezen mislukt.\n");exit(1);}

$marker='LITTYWATCH_PHASE7E6_FIX2_POST_HEADER_STATE_RECOVERY';
if(str_contains($code,$marker)){
    echo "Phase 7E.6 FIX2 staat al geïnstalleerd.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e6-fix2-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/MarketBundleExpander.php');

$anchor=<<<'PHP'
        // LITTYWATCH_PHASE4E_SHARED_MINI_STATE
PHP;

if(!str_contains($code,$anchor)){
    fwrite(STDERR,"ERROR: shared-state anchor niet gevonden.\n");
    exit(1);
}

$insert=<<<'PHP'
        // LITTYWATCH_PHASE7E6_FIX2_POST_HEADER_STATE_RECOVERY
        // "Miniature Zhed Shadowhoof unded/Livia" is first consumed by the
        // normal Miniature-header rule above, leaving:
        //   body  = "Zhed Shadowhoof unded/Livia"
        //   state = null
        // Recover the explicit state from directly after the canonical first member.
        if ($body !== null && $state === null && preg_match(
            '/^(.+?)\s+(unded(?:icated)?|ded(?:icated)?)\s*([\/,])\s*(.+)$/iu',
            trim($body),
            $sm7e6
        )) {
            $state = $this->state($sm7e6[2]);
            $body = trim($sm7e6[1] . $sm7e6[3] . $sm7e6[4]);
        }

PHP;

$code=str_replace($anchor,$insert.$anchor,$code, $count);
if($count!==1){
    copy($backup.'/MarketBundleExpander.php',$file);
    fwrite(STDERR,"ERROR: onverwacht aantal anchors: {$count}\n");
    exit(1);
}

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

echo "OK: LittyWatch V5.2 Phase 7E.6 FIX2 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Post-header dedication recovery actief.\n";
echo "Voorbeeld: body 'Zhed Shadowhoof unded/Livia' -> state=unded + body='Zhed Shadowhoof/Livia'.\n";
