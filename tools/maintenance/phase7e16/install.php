<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);require $root.'/bootstrap.php';$pdo=db();
$writer=$root.'/app/Market/StructuredOfferWriter.php';$semantic=$root.'/app/Parser/SemanticNormalizer.php';
$backup=$root.'/storage/backups/phase7e16-'.date('Ymd-His');@mkdir($backup,0775,true);copy($writer,$backup.'/StructuredOfferWriter.php');copy($semantic,$backup.'/SemanticNormalizer.php');
copy(__DIR__.'/../../../app/Market/Phase7E16MarketSemanticGuard.php',$root.'/app/Market/Phase7E16MarketSemanticGuard.php');
function n16(string $v):string{$v=mb_strtolower(trim(str_replace(['’','´','`'],"'",$v)));$v=preg_replace('/[^a-z0-9]+/u',' ',$v)??$v;return trim(preg_replace('/\s+/u',' ',$v)??$v);} 
function ensure16(PDO $pdo,string $pref,string $name,string $cat,array $aliases=[]):void{
 $st=$pdo->prepare("SELECT key FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");$st->execute([$name]);$key=$st->fetchColumn();
 if($key===false){$key=$pref;$st=$pdo->prepare("SELECT COUNT(*) FROM kb_items WHERE key=?");$st->execute([$key]);if((int)$st->fetchColumn()===0){$pdo->prepare("INSERT INTO kb_items(key,name,category_key,source,source_id,metadata_json,active,updated_at) VALUES(?,?,?,?,?,?,1,?)")->execute([$key,$name,$cat,'phase7e16',null,'{"phase":"7E.16"}',date(DATE_ATOM)]);}}
 foreach(array_unique(array_merge([$name],$aliases)) as $a){$norm=n16($a);$st=$pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");$st->execute([$norm]);if($st->fetchColumn()===false)$pdo->prepare("INSERT INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES(?,?,?,?)")->execute([$key,$a,$norm,'phase7e16']);}
}
$pdo->beginTransaction();try{
 ensure16($pdo,'forget-me-not','Forget Me Not!','inscription',['FMN','Forget Me Not']);ensure16($pdo,'chocolate-bunny','Chocolate Bunny','consumable',['Bunny']);ensure16($pdo,'japan-1st-anniversary-shield','Japan 1st Anniversary Shield','shield',['Japan']);ensure16($pdo,'scythe-grip-of-the-ritualist','Scythe Grip of the Ritualist','weapon_upgrades',['SP +5 Scythe','Spawn +5 Scythe']);ensure16($pdo,'cup-of-the-bison','Cup of the Bison','consumable',['Cup']);ensure16($pdo,'lunar-fortune','Lunar Fortune','consumable',['Luna']);ensure16($pdo,'gift-of-the-traveler','Gift of the Traveler','special',['GooT']);ensure16($pdo,'stalker-s-ration',"Stalker's Ration",'consumable',['Stalk']);ensure16($pdo,'wintergreen-longbow','Wintergreen Longbow','weapon',['WG Longbow']);ensure16($pdo,'grail-of-holy-might','Grail of Holy Might','consumable',['Grail Stacks']);ensure16($pdo,'sapphire','Sapphire','material',['Saph']);ensure16($pdo,'stygian-gemstone','Stygian Gemstone','special',['Styg']);ensure16($pdo,'eternal-blade','Eternal Blade','weapon',['E Blade']);ensure16($pdo,'war-supplies','War Supplies','consumable',['WARSUPPLYS']);ensure16($pdo,'destroyer-core','Destroyer Core','trophy',['Desroyer Cores']);ensure16($pdo,'everlasting-ghostly-hero-tonic','Everlasting Ghostly Hero Tonic','tonic',['EL Ghostly Hero']);
 $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"ERROR: ".$e->getMessage()."\n");exit(1);} 
$code=file_get_contents($semantic);
if(!str_contains($code,'LITTYWATCH_PHASE7E16_ELITE_TOME_LISTS')){
 $p=strpos($code,'LITTYWATCH_PHASE7E15_DOA_GEMS');if($p===false)$p=strpos($code,'LITTYWATCH_PHASE7E14_ELITE_TOME_POSITIVE_LIST');if($p===false){fwrite(STDERR,"ERROR: semantic marker niet gevonden.\n");exit(1);} $e=strpos($code,"\n",$p);
 $block=<<<'BLOCK'

        // LITTYWATCH_PHASE7E16_ELITE_TOME_LISTS
        $text = preg_replace_callback('/\belit(?:e)?\s+tomb?e?s?\s+((?:n|w|a|r|mo|me|e|el|rt|d|p)(?:\s*,?\s+(?:n|w|a|r|mo|me|e|el|rt|d|p))+)\b/iu', static function(array $m): string {
            $map=['n'=>'Necromancer Elite Tome','w'=>'Warrior Elite Tome','a'=>'Assassin Elite Tome','r'=>'Ranger Elite Tome','mo'=>'Monk Elite Tome','me'=>'Mesmer Elite Tome','e'=>'Elementalist Elite Tome','el'=>'Elementalist Elite Tome','rt'=>'Ritualist Elite Tome','d'=>'Dervish Elite Tome','p'=>'Paragon Elite Tome'];
            $out=[];foreach(preg_split('/[\s,]+/u',trim((string)$m[1]))?:[] as $t){$k=mb_strtolower(trim($t));if(isset($map[$k]))$out[]=$map[$k];}
            return $out!==[]?implode(' | ',array_values(array_unique($out))):(string)$m[0];
        }, $text) ?? $text;
        // LITTYWATCH_PHASE7E16_CONFIRMED_SHORTHAND
        $text = preg_replace('/\bFMN\b/iu','Forget Me Not!',$text) ?? $text;
        $text = preg_replace('/\bWG\s+Longbow\b/iu','Wintergreen Longbow',$text) ?? $text;
        $text = preg_replace('/\bWARSUPPLYS\b/iu','War Supplies',$text) ?? $text;
        $text = preg_replace('/\bDesroyer\s+Cores?\b/iu','Destroyer Core',$text) ?? $text;
        $text = preg_replace('/\bE\s+Blade\b/iu','Eternal Blade',$text) ?? $text;
        $text = preg_replace('/\bEL\s+Ghostly\s+Hero\b/iu','Everlasting Ghostly Hero Tonic',$text) ?? $text;
BLOCK;
 $code=substr($code,0,$e+1).$block.substr($code,$e+1);file_put_contents($semantic,$code);
}
$code=file_get_contents($writer);
if(!str_contains($code,'LITTYWATCH_PHASE7E16_PREINSERT_MARKET_SEMANTICS')){$needle="if(\$r['quality_status']==='accepted'){";$p=strpos($code,$needle);if($p===false){fwrite(STDERR,"ERROR: writer marker niet gevonden.\n");exit(1);} $block="     // LITTYWATCH_PHASE7E16_PREINSERT_MARKET_SEMANTICS\n     \$r['_message']=(string)(\$message??'');\n     \$r=(new Phase7E16MarketSemanticGuard(\$this->pdo))->repair(\$r);\n     unset(\$r['_message']);\n\n";$code=substr($code,0,$p).$block.substr($code,$p);file_put_contents($writer,$code);} 
echo "OK: LittyWatch V5.2 Phase 7E.16 geïnstalleerd.\nBackup: {$backup}\nBlue Drinks bewust nog niet gemapt.\n";
