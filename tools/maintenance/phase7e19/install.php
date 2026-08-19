<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$pdo=db();
$writer=$root.'/app/Market/StructuredOfferWriter.php';
$semantic=$root.'/app/Parser/SemanticNormalizer.php';
foreach([$writer,$semantic] as $f){if(!is_file($f)){fwrite(STDERR,"ERROR: ontbreekt: {$f}\n");exit(1);}}
$backup=$root.'/storage/backups/phase7e19-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($writer,$backup.'/StructuredOfferWriter.php');
copy($semantic,$backup.'/SemanticNormalizer.php');
copy(__DIR__.'/../../../app/Market/Phase7E19ContextGuard.php',$root.'/app/Market/Phase7E19ContextGuard.php');

function n19(string $v):string{
 $v=mb_strtolower(trim(str_replace(['’','´','`'],"'",$v)));
 $v=preg_replace('/[^a-z0-9]+/u',' ',$v)??$v;
 return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function ensure19(PDO $pdo,string $key,string $name,string $category,array $aliases=[]):void{
 $st=$pdo->prepare("SELECT key FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");
 $st->execute([$name]); $existing=$st->fetchColumn();
 if($existing===false){
   $st=$pdo->prepare("SELECT COUNT(*) FROM kb_items WHERE key=?");$st->execute([$key]);
   if((int)$st->fetchColumn()===0){
     $pdo->prepare("INSERT INTO kb_items(key,name,category_key,source,source_id,metadata_json,active,updated_at) VALUES(?,?,?,?,?,?,1,?)")
         ->execute([$key,$name,$category,'phase7e19',null,'{"phase":"7E.19"}',date(DATE_ATOM)]);
   }
 }else{$key=(string)$existing;}
 foreach(array_unique(array_merge([$name],$aliases)) as $alias){
   $norm=n19($alias);
   $st=$pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");$st->execute([$norm]);
   if($st->fetchColumn()===false){
      $pdo->prepare("INSERT INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES(?,?,?,?)")
          ->execute([$key,$alias,$norm,'phase7e19']);
   }
 }
}
$pdo->beginTransaction();
try{
 ensure19($pdo,'market-rock-candy-stack','Rock Candy Stack','market_metrics',['Rock Stack','Rock Stack!']);
 ensure19($pdo,'armbrace-of-truth','Armbrace of Truth','special',['Armbraces','Armbracess']);
 ensure19($pdo,'naga-pelt','Naga Pelt','trophy',['Naga Pelts']);
 ensure19($pdo,'flame-of-balthazar','Flame of Balthazar','trophy',['Flame Balthaz']);
 ensure19($pdo,'bow-grip-of-the-ranger','Bow Grip of the Ranger','upgrade',['bow of the ranger']);
 $pdo->commit();
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 fwrite(STDERR,"ERROR: KB update mislukt: ".$e->getMessage()."\n");exit(1);
}

$code=file_get_contents($semantic);
if(!str_contains($code,'LITTYWATCH_PHASE7E19_EL_TONIC_CONTEXT')){
 $marker='LITTYWATCH_PHASE7E18_HONEYCOMB_CUPCAKE_SPLIT';
 $p=strpos($code,$marker);
 if($p===false){$marker='LITTYWATCH_PHASE7E16_CONFIRMED_SHORTHAND';$p=strpos($code,$marker);}
 if($p===false){fwrite(STDERR,"ERROR: SemanticNormalizer marker niet gevonden.\n");exit(1);}
 $lineEnd=strpos($code,"\n",$p);
 $block=<<<'PHP'

        // LITTYWATCH_PHASE7E19_EL_TONIC_CONTEXT
        if (preg_match('/\bEL\s+TONICS?\b/iu', $text)) {
            $text = preg_replace('/\bQUEEN\s+SALMA\b/iu', 'Everlasting Princess Salma Tonic', $text) ?? $text;
            $text = preg_replace('/\bPRINCE\s+RURIK\b/iu', 'Everlasting Prince Rurik Tonic', $text) ?? $text;
            $text = preg_replace('/\bKUUNA(?:VANG)?\b/iu', 'Everlasting Kuunavang Tonic', $text) ?? $text;
        }

        // LITTYWATCH_PHASE7E19_DOA_GEMS_NO_TITAN
        $text = preg_replace(
            '/\bgems?\s+(\d+(?:[.,]\d+)?\s*(?:e|a|k)\/?ea)\s*\(\s*no\s+titans?\s*\)/iu',
            'Margonite Gemstone $1 | Stygian Gemstone $1 | Torment Gemstone $1',
            $text
        ) ?? $text;

        $text = preg_replace('/\bdrake\s+ka\b/iu','Drake Kabob',$text) ?? $text;
        $text = preg_replace('/\bflame\s+balthaz\b/iu','Flame of Balthazar',$text) ?? $text;
PHP;
 $code=substr($code,0,$lineEnd+1).$block.substr($code,$lineEnd+1);
 file_put_contents($semantic,$code);
}

$code=file_get_contents($writer);
if(!str_contains($code,'LITTYWATCH_PHASE7E19_PREINSERT_CONTEXT')){
 $needle="if(\$r['quality_status']==='accepted'){";
 $p=strpos($code,$needle);
 if($p===false){fwrite(STDERR,"ERROR: accepted branch niet gevonden.\n");exit(1);}
 $block="     // LITTYWATCH_PHASE7E19_PREINSERT_CONTEXT\n"
       ."     \$r['_message']=(string)(\$message??'');\n"
       ."     \$r=(new Phase7E19ContextGuard(\$this->pdo))->repair(\$r);\n"
       ."     unset(\$r['_message']);\n\n";
 $code=substr($code,0,$p).$block.substr($code,$p);
 file_put_contents($writer,$code);
}

echo "OK: LittyWatch V5.2 Phase 7E.19 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E19ContextGuard.php\n";
echo "  php -l app/Parser/SemanticNormalizer.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e19/smoke-test.php\n";
