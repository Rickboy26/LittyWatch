<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$pdo=db();
$writer=$root.'/app/Market/StructuredOfferWriter.php';
if(!is_file($writer)){fwrite(STDERR,"ERROR: StructuredOfferWriter.php ontbreekt.\n");exit(1);}
$backup=$root.'/storage/backups/phase7e12-fix2-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($writer,$backup.'/StructuredOfferWriter.php');

$src=__DIR__.'/../../../app/Market/Phase7E12AlcoholMetricGuard.php';
$dst=$root.'/app/Market/Phase7E12AlcoholMetricGuard.php';
copy($src,$dst);

function n12f2(string $v):string{
    $v=mb_strtolower(trim(str_replace(['’','´','`'],"'",$v)));
    $v=preg_replace('/[^a-z0-9]+/u',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}

$pdo->beginTransaction();
try{
    $now=date(DATE_ATOM);
    $st=$pdo->query("SELECT COUNT(*) FROM kb_items WHERE key='market-points-alcohol'");
    if((int)$st->fetchColumn()===0){
        $pdo->prepare("INSERT INTO kb_items(key,name,category_key,source,source_id,metadata_json,active,updated_at) VALUES('market-points-alcohol','Alcohol Points','market_metrics','phase7e12-fix2',NULL,'{}',1,?)")->execute([$now]);
    }else{
        $pdo->prepare("UPDATE kb_items SET name='Alcohol Points',category_key='market_metrics',active=1,updated_at=? WHERE key='market-points-alcohol'")->execute([$now]);
    }

    foreach(['Alcohol Point','Alcohol Points','alc stack','alc stacks','1pt alc','1 pt alc','1point alch','1 point alch','1point alc','1 point alc'] as $alias){
        $norm=n12f2($alias);
        $st=$pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");
        $st->execute([$norm]);
        $owner=$st->fetchColumn();

        if($owner===false){
            $pdo->prepare("INSERT INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES('market-points-alcohol',?,?,?)")->execute([$alias,$norm,'phase7e12-fix2']);
        }elseif((string)$owner==='alcohol-point'){
            $pdo->prepare("UPDATE kb_aliases SET item_key='market-points-alcohol',alias=?,source='phase7e12-fix2' WHERE normalized_alias=? AND item_key='alcohol-point'")->execute([$alias,$norm]);
        }
    }

    $pdo->prepare("DELETE FROM kb_aliases WHERE item_key='alcohol-point'")->execute();
    $pdo->prepare("DELETE FROM kb_items WHERE key='alcohol-point'")->execute();
    $pdo->commit();
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,"ERROR: ".$e->getMessage()."\n");exit(1);
}

$code=file_get_contents($writer);
if(!str_contains($code,'LITTYWATCH_PHASE7E12_FIX2_ALCOHOL_METRIC')){
    $needle="if(\$r['quality_status']==='accepted'){";
    $p=strpos($code,$needle);
    if($p===false){fwrite(STDERR,"ERROR: accepted branch niet gevonden.\n");exit(1);}
    $block="     // LITTYWATCH_PHASE7E12_FIX2_ALCOHOL_METRIC\n     \$r=(new Phase7E12AlcoholMetricGuard(\$this->pdo))->repair(\$r);\n\n";
    $code=substr($code,0,$p).$block.substr($code,$p);
    file_put_contents($writer,$code);
}

echo "OK: LittyWatch V5.2 Phase 7E.12 FIX2 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
