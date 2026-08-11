<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/Catalog.php';
if(!is_file($file)){fwrite(STDERR,"ERROR: Catalog.php ontbreekt.\n");exit(1);}
$backup=$root.'/storage/backups/phase7a-fix1-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/Catalog.php');
$code=file_get_contents($file);
if(str_contains($code,'LITTYWATCH_PHASE7A_LEARNED_ALIASES')){echo "Phase 7A staat al in Catalog.php.\n";exit;}
$needle="                $this->items = $this->mergeItems($this->items, $mapped);\n";
$count=substr_count($code,$needle);
if($count!==1){fwrite(STDERR,"ERROR: Catalog anchor verwacht 1x, gevonden {$count}x.\n");exit(1);}
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
                        $this->items[$idx]['aliases']=array_values(array_unique(array_merge($existing,$learnedByKey[$key])));
                    }
                } catch (\Throwable $e) {
                    // deploy-safe
                }
PHP;
$new=str_replace($needle,$patch,$code);
if(file_put_contents($file,$new)===false){copy($backup.'/Catalog.php',$file);fwrite(STDERR,"ERROR: write mislukt; rollback.\n");exit(1);}
echo "OK: Phase 7A FIX1 geïnstalleerd.\nBackup: {$backup}\n";
echo "Draai: php -l app/Parser/Catalog.php\n";
echo "Daarna: php tools/maintenance/phase7a/smoke-test.php\n";
