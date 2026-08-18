<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$pdo=db();
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$semantic=$root.'/app/Parser/SemanticNormalizer.php';
if(!is_file($semantic)){fwrite(STDERR,"ERROR: SemanticNormalizer.php ontbreekt.\n");exit(1);}
$backup=$root.'/storage/backups/phase7e11-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($semantic,$backup.'/SemanticNormalizer.php');

function n11(string $v):string{
    $v=mb_strtolower(trim(str_replace(['’','´','`'],"'",$v)));
    $v=preg_replace('/[^a-z0-9]+/u',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function add11(PDO $pdo,string $key,string $name,string $category,array $aliases):void{
    $now=date(DATE_ATOM);
    $st=$pdo->prepare("SELECT 1 FROM kb_items WHERE key=?");
    $st->execute([$key]);
    if(!$st->fetchColumn()){
        $pdo->prepare("INSERT INTO kb_items(key,name,category_key,source,source_id,metadata_json,active,updated_at) VALUES(?,?,?,?,?,?,1,?)")
            ->execute([$key,$name,$category,'phase7e11',null,'{"phase":"7E.11"}',$now]);
    }else{
        $pdo->prepare("UPDATE kb_items SET name=?,active=1,updated_at=? WHERE key=?")->execute([$name,$now,$key]);
    }
    foreach(array_values(array_unique(array_merge([$name],$aliases))) as $alias){
        $norm=n11($alias);
        $st=$pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");
        $st->execute([$norm]);
        $owner=$st->fetchColumn();
        if($owner===false){
            $pdo->prepare("INSERT INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES(?,?,?,?)")
                ->execute([$key,$alias,$norm,'phase7e11']);
        }elseif((string)$owner!==$key){
            echo "SKIP alias-conflict: {$alias} -> {$owner}\n";
        }
    }
}

$pdo->beginTransaction();
try{
    add11($pdo,'kazhad-s-fortune',"Kazhad's Fortune",'weapons',["Kazhad's Fortune",'Kazhad Fortune','Kazhads Fortune']);
    add11($pdo,'superior-rune-of-holding','Superior Rune of Holding','upgrades',['Superior Rune of Holding','Sup Rune of Holding','Sup RoH']);
    add11($pdo,'rune-of-belt-holding','Rune of Belt Holding','upgrades',['Rune of Belt Holding','Belt Holding Rune']);
    $pdo->commit();
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,"ERROR: KB update mislukt: ".$e->getMessage()."\n");exit(1);
}

$code=file_get_contents($semantic);
if(!str_contains($code,'LITTYWATCH_PHASE7E11_RESIDUAL_ALIASES')){
    $marker='LITTYWATCH_PHASE7E10_CELESTAL_STAFF';
    $p=strpos($code,$marker);
    if($p===false){$marker='LITTYWATCH_PHASE7E9_REGULAR_TOME_LIST';$p=strpos($code,$marker);}
    if($p===false){fwrite(STDERR,"ERROR: SemanticNormalizer marker niet gevonden.\n");exit(1);}
    $lineEnd=strpos($code,"\n",$p);
    $block=<<<'PHP'

        // LITTYWATCH_PHASE7E11_RESIDUAL_ALIASES
        $text = preg_replace('/\bm4ms\b/iu', 'Measure for Measure', $text) ?? $text;
        $text = preg_replace('/\bsup(?:erior)?\s+rune\s+of\s+holding\b/iu', 'Superior Rune of Holding', $text) ?? $text;
        $text = preg_replace('/\brune\s+of\s+belt\s+holding\b/iu', 'Rune of Belt Holding', $text) ?? $text;
        $text = preg_replace('/\b1\s*(?:point|pt)\s+alch?\b/iu', 'Alcohol Points', $text) ?? $text;
PHP;
    $code=substr($code,0,$lineEnd+1).$block.substr($code,$lineEnd+1);
    file_put_contents($semantic,$code);
}

echo "OK: LittyWatch V5.2 Phase 7E.11 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Draai nu:\n";
echo "  php -l app/Parser/SemanticNormalizer.php\n";
echo "  php tools/maintenance/phase7e11/smoke-test.php\n";
