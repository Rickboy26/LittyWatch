<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$fail=0;
function c20(bool $ok,string $label,mixed $actual=null):void{
 global $fail;
 echo ($ok?'OK   ':'FAIL ').$label;
 if(!$ok&&$actual!==null)echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
 echo PHP_EOL;
 if(!$ok)$fail++;
}
$g=new \LittyWatch\Market\Phase7E20ResidualSemanticsGuard(db());

$tests=[
 ['bleu dyes stack 50a','bleu-dyes','Blue Dye'],
 ['3 Ancient Armor','ancient-armor','Ancient Armor Remnant'],
 ['3 Glad Boxes','glad-boxes',"Gladiator's Zaishen Strongbox"],
];
foreach($tests as [$seg,$key,$expected]){
 $out=$g->repair(['item'=>$seg,'item_key'=>$key,'raw_segment'=>$seg,'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved']);
 c20(($out['item']??'')===$expected,$seg.' => '.$expected,$out['item']??null);
}

$titan=$g->repair([
 'item'=>'titan','item_key'=>'titan','raw_segment'=>'titan 2e/ea',
 '_message'=>'wts gems 1e/ea | titan 2e/ea',
 'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved'
]);
c20(($titan['item']??'')==='Titan Gemstone','Titan DoA context',$titan['item']??null);

$stuff=$g->repair([
 'item'=>'STUFF','item_key'=>'stuff','raw_segment'=>'STUFF',
 'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved'
]);
c20(($stuff['quality_status']??'')==='rejected','STUFF rejected',$stuff['quality_reason']??null);

$ana=$g->repair([
 'item'=>'"Aptitude not Attitude"','item_key'=>'aptitude-not-attitude',
 'raw_segment'=>'staff inscription for necro',
 'quality_status'=>'review','quality_reason'=>'low_confidence'
]);
c20(($ana['quality_reason']??'')==='collection_or_market_request','generic staff inscription blocked',$ana['quality_reason']??null);

$sem=file_get_contents($root.'/app/Parser/SemanticNormalizer.php');
c20(str_contains((string)$sem,'LITTYWATCH_PHASE7E20_DOA_GEM_PRICE_SPLIT'),'DoA gem split marker aanwezig');

$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
c20(str_contains((string)$writer,'LITTYWATCH_PHASE7E20_PREINSERT_RESIDUAL'),'writer 7E.20 marker aanwezig');

echo PHP_EOL;
if($fail){echo "Phase 7E.20 smoke-test: {$fail} fout(en).\n";exit(1);}
echo "Phase 7E.20 smoke-test volledig OK.\n";
echo "Daarna live-market reset voor zuivere meting; geen reparse-all.\n";
