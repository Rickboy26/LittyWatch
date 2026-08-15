<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/SemanticNormalizer.php';

if(!is_file($file)){
    fwrite(STDERR,"ERROR: SemanticNormalizer.php ontbreekt.\n");
    exit(1);
}

$code=file_get_contents($file);
if($code===false){
    fwrite(STDERR,"ERROR: lezen mislukt.\n");
    exit(1);
}

$marker='LITTYWATCH_PHASE7E3_FIX2A_PRINCE_RURIK_IDENTITY_SPLIT';
if(str_contains($code,$marker)){
    echo "Phase 7E.3 FIX2a staat al in SemanticNormalizer.php.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e3-fix2a-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/SemanticNormalizer.php');

/* Split the bad combined mapping:
 * (undead prince ... | prince rurik) => Miniature Undead Prince Rurik
 * into two independent mappings.
 */
$old="            '(?:undead\\s+prince(?:\\s+rurik)?|prince\\s+rurik)' => 'Miniature Undead Prince Rurik',";

$new=<<<'PHP'
            // LITTYWATCH_PHASE7E3_FIX2A_PRINCE_RURIK_IDENTITY_SPLIT
            'undead\s+prince(?:\s+rurik)?' => 'Miniature Undead Prince Rurik',
            'prince\s+rurik' => 'Miniature Prince Rurik',
PHP;

if(!str_contains($code,$old)){
    copy($backup.'/SemanticNormalizer.php',$file);
    fwrite(STDERR,"ERROR: gecombineerde Prince Rurik mapping niet gevonden.\n");
    exit(1);
}

$code=str_replace($old,$new,$code);

/* Ensure no later cleanup accidentally rewrites Miniature Prince Rurik
 * to the undead identity.
 */
if(preg_match('/prince\\s\+rurik.*Miniature Undead Prince Rurik/su',$code)){
    // Allow legitimate undead-prince lines, but reject the exact simple Prince Rurik collision.
    if(str_contains($code,"'prince\\s+rurik' => 'Miniature Undead Prince Rurik'")){
        copy($backup.'/SemanticNormalizer.php',$file);
        fwrite(STDERR,"ERROR: Prince Rurik collision staat nog in SemanticNormalizer.php.\n");
        exit(1);
    }
}

if(file_put_contents($file,$code)===false){
    copy($backup.'/SemanticNormalizer.php',$file);
    fwrite(STDERR,"ERROR: schrijven mislukt; backup teruggezet.\n");
    exit(1);
}

exec('/usr/bin/php -l '.escapeshellarg($file),$out,$rc);
if($rc!==0){
    copy($backup.'/SemanticNormalizer.php',$file);
    fwrite(STDERR,"ERROR: syntaxfout; backup teruggezet.\n");
    fwrite(STDERR,implode("\n",$out)."\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.3 FIX2a geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Prince Rurik en Undead Prince zijn nu gescheiden in SemanticNormalizer.\n";
