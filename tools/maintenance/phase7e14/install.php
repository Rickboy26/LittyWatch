<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$pdo=db();
$writer=$root.'/app/Market/StructuredOfferWriter.php';
$semantic=$root.'/app/Parser/SemanticNormalizer.php';
foreach([$writer,$semantic] as $f){if(!is_file($f)){fwrite(STDERR,"ERROR: ontbreekt: {$f}\n");exit(1);}}
$backup=$root.'/storage/backups/phase7e14-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($writer,$backup.'/StructuredOfferWriter.php');
copy($semantic,$backup.'/SemanticNormalizer.php');
copy(__DIR__.'/../../../app/Market/Phase7E14ResidualGuard.php',$root.'/app/Market/Phase7E14ResidualGuard.php');

function n14(string $v):string{
 $v=mb_strtolower(trim(str_replace(['’','´','`'],"'",$v)));
 $v=preg_replace('/[^a-z0-9]+/u',' ',$v)??$v;
 return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function ensure14(PDO $pdo,string $key,string $name,string $category,array $aliases):void{
 $now=date(DATE_ATOM);
 $st=$pdo->prepare("SELECT COUNT(*) FROM kb_items WHERE key=?");$st->execute([$key]);
 if((int)$st->fetchColumn()===0){
  $pdo->prepare("INSERT INTO kb_items(key,name,category_key,source,source_id,metadata_json,active,updated_at) VALUES(?,?,?,?,?,?,1,?)")
      ->execute([$key,$name,$category,'phase7e14',null,'{"phase":"7E.14"}',$now]);
 }else{
  $pdo->prepare("UPDATE kb_items SET name=?,active=1,updated_at=? WHERE key=?")->execute([$name,$now,$key]);
 }
 foreach(array_unique(array_merge([$name],$aliases)) as $alias){
  $norm=n14($alias);
  $st=$pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");$st->execute([$norm]);
  if($st->fetchColumn()===false){
   $pdo->prepare("INSERT INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES(?,?,?,?)")->execute([$key,$alias,$norm,'phase7e14']);
  }
 }
}
$pdo->beginTransaction();
try{
 ensure14($pdo,'unnatural-seed','Unnatural Seed','collectibles',['Unnatural Seeds','abnormal seed','abnormal seeds']);
 ensure14($pdo,'birdseye','Birdseye','unknown',['Bords Eye','Bords Eyes']);
 $pdo->commit();
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 fwrite(STDERR,"ERROR: KB update mislukt: ".$e->getMessage()."\n");exit(1);
}

$code=file_get_contents($semantic);
if(!str_contains($code,'LITTYWATCH_PHASE7E14_ELITE_TOME_POSITIVE_LIST')){
 $marker='LITTYWATCH_PHASE7E11_RESIDUAL_ALIASES';
 $p=strpos($code,$marker);
 if($p===false){$marker='LITTYWATCH_PHASE7E9_REGULAR_TOME_LIST';$p=strpos($code,$marker);}
 if($p===false){fwrite(STDERR,"ERROR: SemanticNormalizer marker niet gevonden.\n");exit(1);}
 $lineEnd=strpos($code,"\n",$p);
 $block=<<<'PHP'

        // LITTYWATCH_PHASE7E14_ELITE_TOME_POSITIVE_LIST
        $text = preg_replace_callback(
            '/\belite\s+tomes?\s+((?:el|e|rt)(?:\s*,\s*(?:el|e|rt))+)\b/iu',
            static function(array $m): string {
                $map=['el'=>'Elementalist Elite Tome','e'=>'Elementalist Elite Tome','rt'=>'Ritualist Elite Tome'];
                $out=[];
                foreach(preg_split('/\s*,\s*/u',(string)$m[1])?:[] as $token){
                    $k=mb_strtolower(trim($token));
                    if(isset($map[$k]))$out[]=$map[$k];
                }
                return $out!==[]?implode(' | ',array_values(array_unique($out))):(string)$m[0];
            },
            $text
        ) ?? $text;
PHP;
 $code=substr($code,0,$lineEnd+1).$block.substr($code,$lineEnd+1);
 file_put_contents($semantic,$code);
}

$code=file_get_contents($writer);
if(!str_contains($code,'LITTYWATCH_PHASE7E14_PREINSERT_RESIDUAL')){
 $needle="if(\$r['quality_status']==='accepted'){";
 $p=strpos($code,$needle);
 if($p===false){fwrite(STDERR,"ERROR: accepted branch niet gevonden.\n");exit(1);}
 $block="     // LITTYWATCH_PHASE7E14_PREINSERT_RESIDUAL\n     \$r=(new Phase7E14ResidualGuard(\$this->pdo))->repair(\$r);\n\n";
 $code=substr($code,0,$p).$block.substr($code,$p);
 file_put_contents($writer,$code);
}

echo "OK: LittyWatch V5.2 Phase 7E.14 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
