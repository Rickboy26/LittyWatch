<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$fail=0;
function ck(bool $ok,string $label):void{global $fail;echo($ok?'OK   ':'FAIL ').$label.PHP_EOL;if(!$ok)$fail++;}

$r=db()->query("SELECT name,category_key,active FROM kb_items WHERE key='market-points-alcohol'")->fetch(PDO::FETCH_ASSOC);
ck(($r['name']??'')==='Alcohol Points','canonical naam Alcohol Points');
ck(($r['category_key']??'')==='market_metrics','category market_metrics');
ck((int)($r['active']??0)===1,'metric actief');
ck((int)db()->query("SELECT COUNT(*) FROM kb_items WHERE key='alcohol-point'")->fetchColumn()===0,'fake alcohol-point verwijderd');

foreach(['alc stacks','alc stack','1pt alc','1point alch','alcohol points'] as $alias){
    $norm=mb_strtolower($alias);
    $norm=preg_replace('/[^a-z0-9]+/u',' ',$norm)??$norm;
    $norm=trim(preg_replace('/\s+/u',' ',$norm)??$norm);
    $st=db()->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");
    $st->execute([$norm]);
    ck($st->fetchColumn()==='market-points-alcohol','alias '.$alias.' -> market metric');
}

$g=new \LittyWatch\Market\Phase7E12AlcoholMetricGuard(db());
$out=$g->repair(['item'=>'Alcohol Point','item_key'=>'alcohol-point','raw_segment'=>'alc stacks 2e ea','quality_status'=>'review','quality_reason'=>'strict_catalog_missing']);
ck(($out['item_key']??'')==='market-points-alcohol','guard canonical key');
ck(($out['quality_reason']??'')==='catalog_match','guard catalog_match');

echo PHP_EOL;
if($fail){echo "Phase 7E.12 FIX2 smoke-test: {$fail} fout(en).\n";exit(1);}
echo "Phase 7E.12 FIX2 smoke-test volledig OK.\n";
