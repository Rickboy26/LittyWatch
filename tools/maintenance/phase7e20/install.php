<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$pdo=db();
$writer=$root.'/app/Market/StructuredOfferWriter.php';
$semantic=$root.'/app/Parser/SemanticNormalizer.php';
foreach([$writer,$semantic] as $f){if(!is_file($f)){fwrite(STDERR,"ERROR: ontbreekt: {$f}\n");exit(1);}}
$backup=$root.'/storage/backups/phase7e20-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($writer,$backup.'/StructuredOfferWriter.php');
copy($semantic,$backup.'/SemanticNormalizer.php');
copy(__DIR__.'/../../../app/Market/Phase7E20ResidualSemanticsGuard.php',$root.'/app/Market/Phase7E20ResidualSemanticsGuard.php');

function n20(string $v):string{
 $v=mb_strtolower(trim(str_replace(['’','´','`'],"'",$v)));
 $v=preg_replace('/[^a-z0-9]+/u',' ',$v)??$v;
 return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function alias20(PDO $pdo,string $target,array $aliases):void{
 $st=$pdo->prepare("SELECT key FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");
 $st->execute([$target]); $key=$st->fetchColumn();
 if($key===false){echo "SKIP target niet in KB: {$target}\n";return;}
 foreach($aliases as $alias){
   $norm=n20($alias);
   $st=$pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");$st->execute([$norm]);
   if($st->fetchColumn()===false){
     $pdo->prepare("INSERT INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES(?,?,?,?)")
         ->execute([(string)$key,$alias,$norm,'phase7e20']);
   }
 }
}
$pdo->beginTransaction();
try{
 alias20($pdo,'Blue Dye',['blue dyes','bleu dye','bleu dyes']);
 alias20($pdo,'Ancient Armor Remnant',['Ancient Armor']);
 alias20($pdo,"Gladiator's Zaishen Strongbox",['Glad Box','Glad Boxes']);
 alias20($pdo,'Titan Gemstone',['Titan']);
 $pdo->commit();
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 fwrite(STDERR,"ERROR: alias update mislukt: ".$e->getMessage()."\n");exit(1);
}

$code=file_get_contents($semantic);
if(!str_contains($code,'LITTYWATCH_PHASE7E20_DOA_GEM_PRICE_SPLIT')){
 $marker='LITTYWATCH_PHASE7E19_DOA_GEMS_NO_TITAN';
 $p=strpos($code,$marker);
 if($p===false){$marker='LITTYWATCH_PHASE7E15_DOA_GEMS';$p=strpos($code,$marker);}
 if($p===false){fwrite(STDERR,"ERROR: SemanticNormalizer marker niet gevonden.\n");exit(1);}
 $lineEnd=strpos($code,"\n",$p);
 $block=<<<'PHP'

        // LITTYWATCH_PHASE7E20_DOA_GEM_PRICE_SPLIT
        $text = preg_replace(
            '/\bgems?\s+(\d+(?:[.,]\d+)?\s*(?:e|a|k)\/?ea)\s*\|\s*titan\s+(\d+(?:[.,]\d+)?\s*(?:e|a|k)\/?ea)/iu',
            'Margonite Gemstone $1 | Stygian Gemstone $1 | Torment Gemstone $1 | Titan Gemstone $2',
            $text
        ) ?? $text;

        $text = preg_replace('/\bbleu\s+dyes?\b/iu','Blue Dye',$text) ?? $text;
        $text = preg_replace('/\bblue\s+dyes?\b/iu','Blue Dye',$text) ?? $text;
        $text = preg_replace('/\bAncient\s+Armor\b/iu','Ancient Armor Remnant',$text) ?? $text;
        $text = preg_replace('/\bGlad\s+Boxes?\b/iu',"Gladiator's Zaishen Strongbox",$text) ?? $text;
PHP;
 $code=substr($code,0,$lineEnd+1).$block.substr($code,$lineEnd+1);
 file_put_contents($semantic,$code);
}

$code=file_get_contents($writer);
if(!str_contains($code,'LITTYWATCH_PHASE7E20_PREINSERT_RESIDUAL')){
 $needle="if(\$r['quality_status']==='accepted'){";
 $p=strpos($code,$needle);
 if($p===false){fwrite(STDERR,"ERROR: accepted branch niet gevonden.\n");exit(1);}
 $block="     // LITTYWATCH_PHASE7E20_PREINSERT_RESIDUAL\n"
       ."     \$r['_message']=(string)(\$message??'');\n"
       ."     \$r=(new Phase7E20ResidualSemanticsGuard(\$this->pdo))->repair(\$r);\n"
       ."     unset(\$r['_message']);\n\n";
 $code=substr($code,0,$p).$block.substr($code,$p);
 file_put_contents($writer,$code);
}

echo "OK: LittyWatch V5.2 Phase 7E.20 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E20ResidualSemanticsGuard.php\n";
echo "  php -l app/Parser/SemanticNormalizer.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e20/smoke-test.php\n";
