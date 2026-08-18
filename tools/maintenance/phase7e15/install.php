<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$pdo=db();
$writer=$root.'/app/Market/StructuredOfferWriter.php';
$semantic=$root.'/app/Parser/SemanticNormalizer.php';
foreach([$writer,$semantic] as $f){if(!is_file($f)){fwrite(STDERR,"ERROR: ontbreekt: {$f}\n");exit(1);}}
$backup=$root.'/storage/backups/phase7e15-'.date('Ymd-His');
@mkdir($backup,0775,true);copy($writer,$backup.'/StructuredOfferWriter.php');copy($semantic,$backup.'/SemanticNormalizer.php');
copy(__DIR__.'/../../../app/Market/Phase7E15MarketSemanticGuard.php',$root.'/app/Market/Phase7E15MarketSemanticGuard.php');
function n15(string $v):string{$v=mb_strtolower(trim(str_replace(['’','´','`'],"'",$v)));$v=preg_replace('/[^a-z0-9]+/u',' ',$v)??$v;return trim(preg_replace('/\s+/u',' ',$v)??$v);}
function ensure15(PDO $pdo,string $key,string $name,string $category,array $aliases=[]):void{
 $now=date(DATE_ATOM);$st=$pdo->prepare("SELECT COUNT(*) FROM kb_items WHERE key=?");$st->execute([$key]);
 if((int)$st->fetchColumn()===0){$pdo->prepare("INSERT INTO kb_items(key,name,category_key,source,source_id,metadata_json,active,updated_at) VALUES(?,?,?,?,?,?,1,?)")->execute([$key,$name,$category,'phase7e15',null,'{\"phase\":\"7E.15\"}',$now]);}
 else{$pdo->prepare("UPDATE kb_items SET name=?,active=1,updated_at=? WHERE key=?")->execute([$name,$now,$key]);}
 foreach(array_unique(array_merge([$name],$aliases)) as $alias){$norm=n15($alias);$st=$pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");$st->execute([$norm]);if($st->fetchColumn()===false){$pdo->prepare("INSERT INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES(?,?,?,?)")->execute([$key,$alias,$norm,'phase7e15']);}}
}
$pdo->beginTransaction();
try{
 ensure15($pdo,'market-inscribable-golds','Inscribable Golds','market_metrics',['Insc Golds','Inscribable Golds','Insc Gold','Golds']);
 ensure15($pdo,'golden-egg','Golden Egg','consumable',['Egg']);
 ensure15($pdo,'party-beacon','Party Beacon','consumable',['Beacon','Beacons']);
 ensure15($pdo,'battle-isle-iced-tea','Battle Isle Iced Tea','consumable',['Tea','Teas']);
 ensure15($pdo,'delicious-cake','Delicious Cake','consumable',['D-Cake','D-Cakes','D cake','D cakes']);
 $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"ERROR: KB update mislukt: ".$e->getMessage()."\n");exit(1);}
$code=file_get_contents($semantic);
$code=str_replace("'Birthday Cupcake', $text","'Delicious Cake', $text",$code);
if(!str_contains($code,'LITTYWATCH_PHASE7E15_DOA_GEMS')){
 $marker='LITTYWATCH_PHASE7E14_ELITE_TOME_POSITIVE_LIST';$p=strpos($code,$marker);
 if($p===false){$marker='LITTYWATCH_PHASE7E11_RESIDUAL_ALIASES';$p=strpos($code,$marker);}if($p===false){fwrite(STDERR,"ERROR: SemanticNormalizer marker niet gevonden.\n");exit(1);}
 $lineEnd=strpos($code,"\n",$p);
 $block=<<<'PHP'

        // LITTYWATCH_PHASE7E15_DOA_GEMS
        // GW1 trade shorthand: Gems = DoA gems. "(no titan)" means the other three.
        $text = preg_replace(
            '/\bgems?\b(?=\s+\d+(?:[.,]\d+)?\s*(?:e|a|k)\/?ea\s*\(\s*no\s+titan\s*\))/iu',
            'Margonite Gemstone | Stygian Gemstone | Torment Gemstone',
            $text
        ) ?? $text;

        // LITTYWATCH_PHASE7E15_SPEAR_MOD_REORDER
        // q/req + mods belong to the Spear identity, not to a fake item name.
        $text = preg_replace(
            '/\b(?:req|q)\s*(\d{1,2})\s+(\d+%\s*Adrenaline)\s+(\d+\^50%)\s+Spear\b/iu',
            'Spear q$1 $2 $3',
            $text
        ) ?? $text;
PHP;
 $code=substr($code,0,$lineEnd+1).$block.substr($code,$lineEnd+1);
}
file_put_contents($semantic,$code);
$code=file_get_contents($writer);
if(!str_contains($code,'LITTYWATCH_PHASE7E15_PREINSERT_MARKET_SEMANTICS')){
 $needle="if(\$r['quality_status']==='accepted'){";$p=strpos($code,$needle);if($p===false){fwrite(STDERR,"ERROR: accepted branch niet gevonden.\n");exit(1);}
 $block="     // LITTYWATCH_PHASE7E15_PREINSERT_MARKET_SEMANTICS\n     \$r=(new Phase7E15MarketSemanticGuard(\$this->pdo))->repair(\$r);\n\n";$code=substr($code,0,$p).$block.substr($code,$p);file_put_contents($writer,$code);
}
echo "OK: LittyWatch V5.2 Phase 7E.15 geïnstalleerd.\nBackup: {$backup}\n";
