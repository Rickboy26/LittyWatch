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

$marker='LITTYWATCH_PHASE7E6_FIX1_CANONICALIZED_FIRST_MEMBER';
if(str_contains($code,$marker)){
    echo "Phase 7E.6 FIX1 staat al geïnstalleerd.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e6-fix1-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/MarketBundleExpander.php');

$anchor=<<<'PHP'
        // LITTYWATCH_PHASE4E_SHARED_MINI_STATE
        if ($body === null && preg_match('/^(?:wts|wtb|wtt)?\s*(unded(?:icated)?|ded(?:icated)?)\s+(.+[,\/].+)$/iu',trim($text),$sm)) {
            $state = $this->state($sm[1]);
            $body = trim($sm[2]);
        }
PHP;

if(!str_contains($code,$anchor)){
    fwrite(STDERR,"ERROR: shared mini state anchor niet gevonden.\n");
    exit(1);
}

$replacement=<<<'PHP'
        // LITTYWATCH_PHASE4E_SHARED_MINI_STATE
        if ($body === null && preg_match('/^(?:wts|wtb|wtt)?\s*(unded(?:icated)?|ded(?:icated)?)\s+(.+[,\/].+)$/iu',trim($text),$sm)) {
            $state = $this->state($sm[1]);
            $body = trim($sm[2]);
        }

        // LITTYWATCH_PHASE7E6_FIX1_CANONICALIZED_FIRST_MEMBER
        // SemanticNormalizer may canonicalize only the first member before this
        // bundle expander runs:
        //   unded Zhed/Livia
        // becomes:
        //   Miniature Zhed Shadowhoof unded/Livia
        //
        // Recover the shared state and reconstruct the first member so the usual
        // MINIATURES map can distribute dedication to every slash/comma member.
        if ($body === null && preg_match(
            '/^Miniature\s+(.+?)\s+(unded(?:icated)?|ded(?:icated)?)\s*([\/,])\s*(.+)$/iu',
            trim($text),
            $sm
        )) {
            $state = $this->state($sm[2]);
            $body = trim($sm[1] . $sm[3] . $sm[4]);
        }
PHP;

$code=str_replace($anchor,$replacement,$code);

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

echo "OK: LittyWatch V5.2 Phase 7E.6 FIX1 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Canonicalized first-member slash recovery actief.\n";
echo "Voorbeeld: Miniature Zhed Shadowhoof unded/Livia -> shared unded list.\n";
