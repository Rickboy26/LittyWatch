<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$catalog=$root.'/app/Parser/Catalog.php';
$semantic=$root.'/app/Parser/SemanticNormalizer.php';
$writer=$root.'/app/Market/StructuredOfferWriter.php';
$data=$root.'/app/Data/phase4h-items.json';
foreach([$catalog,$semantic,$writer,$data] as $f){if(!is_file($f)){fwrite(STDERR,"ERROR: ontbreekt {$f}\n");exit(1);}}
$stamp=date('Ymd-His');$backup=$root.'/storage/backups/phase4h-'.$stamp;
if(!is_dir($backup)&&!mkdir($backup,0775,true)&&!is_dir($backup)){fwrite(STDERR,"ERROR backupmap\n");exit(1);}
foreach([$catalog,$semantic,$writer] as $f)copy($f,$backup.'/'.basename($f));
function one(string $s,string $n,string $r,string $l):string{$c=substr_count($s,$n);if($c!==1)throw new RuntimeException("Anchor {$l} verwacht 1x, gevonden {$c}x.");return str_replace($n,$r,$s);}
try{
$c=(string)file_get_contents($catalog);
if(!str_contains($c,'LITTYWATCH_PHASE4H_FINAL_CATALOG')){
$a="        \$this->modifiers = \$this->loadJson(\$dataDir . '/modifiers.json');\n";
$c=one($c,$a,"        // LITTYWATCH_PHASE4H_FINAL_CATALOG\n        \$phase4hItemsPath = \$dataDir . '/phase4h-items.json';\n        if (is_file(\$phase4hItemsPath)) {\n            \$this->items = \$this->mergeItems(\$this->items, \$this->loadJson(\$phase4hItemsPath));\n        }\n".$a,'catalog');file_put_contents($catalog,$c);}
$s=(string)file_get_contents($semantic);
if(!str_contains($s,'LITTYWATCH_PHASE4H_FINAL_NORMALIZATION')){
$a="        // LITTYWATCH_PHASE4F_RESIDUAL_NORMALIZATION_END\n";
$r=<<<'PHP'
        // LITTYWATCH_PHASE4H_FINAL_NORMALIZATION
        $text=preg_replace('/\bArtifact\s+flame\b|\bFlame\s+Artifact(?:\s*\(\s*Fire\s+Magic\s*\))?\s+With\s+(?:the\s+)?Eye\b/iu','Flame Artifact',$text)??$text;
        $text=preg_replace('/\b(?:Stack\s+of\s+)?Obi(?:sidian)?\s+Shards?\b/iu','Obsidian Shard',$text)??$text;
        $text=preg_replace('/\bGranite(?:\s+Slabs?)?\b/iu','Granite Slab',$text)??$text;
        $text=preg_replace('/\bshiroken\s+assassin\s+mini(?:ature|pet)?\b/iu',"Miniature Shiro'ken Assassin",$text)??$text;
        // LITTYWATCH_PHASE4H_FINAL_NORMALIZATION_END

PHP;
$s=one($s,$a,$r.$a,'semantic');file_put_contents($semantic,$s);}
$w=(string)file_get_contents($writer);
if(!str_contains($w,'LITTYWATCH_PHASE4H_CLASSIFIER_FIX')){
$old="        || preg_match('/^(?:for\\\\s+1|little\\\\s+john|demrikov)$/iu',\$__lwItem)\n";
$w=one($w,$old,"        // LITTYWATCH_PHASE4H_CLASSIFIER_FIX: no hard-coded item names as service/noise.\n",'service false positives');
$anchor="    \$__lwModifierFragment = !\$__lwInsufficient && !\$__lwCollection && !\$__lwServiceNoise && (\n";
$rep="    // LITTYWATCH_PHASE4H_CLASSIFIER_FIX\n".$anchor."        preg_match('/\\b(?:[345]\\s*(?:sr|soul\\s*reaping|leadership|energy\\s*storage)\\s+for\\s+(?:bow|staff|scepter|wand)|sr\\s*\\+\\s*[345]\\s+for\\s+(?:bow|staff|scepter|wand))\\b/iu',\$__lwText)\n        || ";
$w=one($w,$anchor,$rep,'modifier start');file_put_contents($writer,$w);}
foreach([$catalog,$semantic,$writer] as $f){$o=[];$code=0;exec('php -l '.escapeshellarg($f).' 2>&1',$o,$code);if($code!==0)throw new RuntimeException(implode("\n",$o));}
require $root.'/bootstrap.php';$db=db();new \LittyWatch\Parser\Catalog($root.'/app/Data',$db);
echo "OK: LittyWatch V5.2 Phase 4H geïnstalleerd.\nBackup: {$backup}\n";
}catch(Throwable $e){fwrite(STDERR,"ERROR: ".$e->getMessage()."\nRollback vanuit {$backup}...\n");foreach([$catalog,$semantic,$writer] as $f){$b=$backup.'/'.basename($f);if(is_file($b))@copy($b,$f);}exit(1);}
