<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/bootstrap.php';

$execute = in_array('--execute', $argv, true);
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$clear = [
    'structured_offers',
    'offers',
    'market_intelligence',
    'market_snapshots',
    'ai_offer_validations',
    'alert_events',
    'alerts',
];

function tableExists(PDO $pdo, string $table): bool {
    $st=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}
function countRows(PDO $pdo, string $table): int {
    return (int)$pdo->query('SELECT COUNT(*) FROM "'.str_replace('"','""',$table).'"')->fetchColumn();
}
function mainDbFile(PDO $pdo): ?string {
    foreach($pdo->query('PRAGMA database_list') as $r){
        if(($r['name']??'')==='main'){
            $f=trim((string)($r['file']??''));
            return $f!==''?$f:null;
        }
    }
    return null;
}

echo "=== LittyWatch Fresh Live Market Reset ===\n\n";
echo "BEHOUDEN:\n";
foreach(['messages','kb_items','kb_aliases','parser_learned_aliases','parser_corrections','parser_reviews','alert_rules','watchlist'] as $t){
    if(tableExists($pdo,$t)) printf("  KEEP  %-30s %d\n",$t,countRows($pdo,$t));
}
echo "\nLEEGMAKEN:\n";
$total=0;
foreach($clear as $t){
    if(!tableExists($pdo,$t)){ printf("  SKIP  %-30s ontbreekt\n",$t); continue; }
    $n=countRows($pdo,$t); $total+=$n;
    printf("  CLEAR %-30s %d\n",$t,$n);
}
echo "\nTotaal te verwijderen rijen: {$total}\n";

if(!$execute){
    echo "\nDRY RUN: niets gewijzigd.\n";
    echo "Uitvoeren met:\n  php tools/maintenance/reset-live-market.php --execute\n";
    exit(0);
}

$dbFile=mainDbFile($pdo);
if($dbFile===null || !is_file($dbFile)){
    fwrite(STDERR,"ERROR: SQLite databasebestand niet gevonden; afgebroken.\n");
    exit(1);
}

$backupDir=$root.'/storage/backups/live-market-reset-'.date('Ymd-His');
if(!is_dir($backupDir) && !mkdir($backupDir,0775,true) && !is_dir($backupDir)){
    fwrite(STDERR,"ERROR: backupmap kon niet worden aangemaakt.\n");
    exit(1);
}
$backupFile=$backupDir.'/'.basename($dbFile);
if(!copy($dbFile,$backupFile)){
    fwrite(STDERR,"ERROR: databasebackup mislukt; afgebroken.\n");
    exit(1);
}
echo "\nBackup: {$backupFile}\n";

try{
    $pdo->exec('PRAGMA busy_timeout=10000');
    $pdo->exec('BEGIN IMMEDIATE');
    foreach($clear as $t){
        if(!tableExists($pdo,$t)) continue;
        $q='"'.str_replace('"','""',$t).'"';
        $pdo->exec("DELETE FROM {$q}");
    }
    if(tableExists($pdo,'sqlite_sequence')){
        $marks=implode(',',array_fill(0,count($clear),'?'));
        $st=$pdo->prepare("DELETE FROM sqlite_sequence WHERE name IN ({$marks})");
        $st->execute($clear);
    }
    $pdo->commit();
}catch(Throwable $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,"ERROR: reset mislukt; rollback uitgevoerd: ".$e->getMessage()."\n");
    exit(1);
}

echo "\n=== Na reset ===\n";
foreach($clear as $t){
    if(tableExists($pdo,$t)) printf("  %-30s %d\n",$t,countRows($pdo,$t));
}
echo "\nMessages behouden: ".(tableExists($pdo,'messages')?countRows($pdo,'messages'):0)."\n";
echo "KB items behouden: ".(tableExists($pdo,'kb_items')?countRows($pdo,'kb_items'):0)."\n";
echo "KB aliases behouden: ".(tableExists($pdo,'kb_aliases')?countRows($pdo,'kb_aliases'):0)."\n";
echo "\nOK: live marktdata is schoon.\n";
echo "NIET reparse-all draaien; laat alleen nieuwe collector-data binnenkomen.\n";
