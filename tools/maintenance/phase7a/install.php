<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/Catalog.php';

if(!is_file($file)){
    fwrite(STDERR,"ERROR: Catalog.php ontbreekt.\n");
    exit(1);
}

$backup=$root.'/storage/backups/phase7a-fix2-'.date('Ymd-His');
if(!is_dir($backup) && !mkdir($backup,0775,true) && !is_dir($backup)){
    fwrite(STDERR,"ERROR: backupmap kon niet worden gemaakt.\n");
    exit(1);
}
copy($file,$backup.'/Catalog.php');

$code=file_get_contents($file);
if($code===false){
    fwrite(STDERR,"ERROR: Catalog.php kon niet worden gelezen.\n");
    exit(1);
}

if(str_contains($code,'LITTYWATCH_PHASE7A_LEARNED_ALIASES')){
    echo "Phase 7A learned-alias integratie staat al in Catalog.php.\n";
    echo "Backup: {$backup}\n";
    exit(0);
}

// Literal source-code anchor: no interpolation of $this/$mapped.
$needle = '                $this->items = $this->mergeItems($this->items, $mapped);' . "\n";
$count=substr_count($code,$needle);

if($count!==1){
    fwrite(STDERR,"ERROR: Catalog anchor verwacht 1x, gevonden {$count}x.\n");
    fwrite(STDERR,"Bestand is niet gewijzigd.\n");
    exit(1);
}

$patch=$needle.<<<'PHP'

                // LITTYWATCH_PHASE7A_LEARNED_ALIASES
                try {
                    $learnedStmt=$db->query("
                        SELECT normalized_alias,alias,item_key,confidence
                        FROM parser_learned_aliases
                        WHERE active=1 AND confidence>=0.99
                        ORDER BY confidence DESC,id
                    ");

                    $learnedByKey=[];

                    foreach($learnedStmt as $learned){
                        $alias=trim((string)($learned['alias']??''));
                        $normalized=trim((string)($learned['normalized_alias']??''));
                        $itemKey=trim((string)($learned['item_key']??''));

                        if($alias===''||$normalized===''||$itemKey==='')continue;

                        $compact=preg_replace('/[^a-z0-9]+/iu','',mb_strtolower($normalized))??'';
                        if(mb_strlen($compact)<4)continue;

                        $learnedByKey[$itemKey][]=$alias;
                    }

                    foreach($this->items as $idx=>$catalogItem){
                        $key=trim((string)($catalogItem['key']??''));
                        if($key===''||!isset($learnedByKey[$key]))continue;

                        $existing=$catalogItem['aliases']??[];
                        if(!is_array($existing))$existing=[];

                        $this->items[$idx]['aliases']=array_values(array_unique(array_merge(
                            $existing,
                            $learnedByKey[$key]
                        )));
                    }
                } catch (\Throwable $e) {
                    // Deploy-safe: parser blijft beschikbaar als learning-table ontbreekt.
                }
PHP;

$new=str_replace($needle,$patch,$code);

if(file_put_contents($file,$new)===false){
    copy($backup.'/Catalog.php',$file);
    fwrite(STDERR,"ERROR: schrijven mislukt; rollback uitgevoerd.\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7A FIX2 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Draai nu:\n";
echo "  php -l app/Parser/Catalog.php\n";
echo "  php tools/maintenance/phase7a/smoke-test.php\n";
