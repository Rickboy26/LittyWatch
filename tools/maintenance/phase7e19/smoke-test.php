<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$fail=0;
function c19(bool $ok,string $label,mixed $actual=null):void{
 global $fail;
 echo ($ok?'OK   ':'FAIL ').$label;
 if(!$ok&&$actual!==null)echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
 echo PHP_EOL;
 if(!$ok)$fail++;
}
$g=new \LittyWatch\Market\Phase7E19ContextGuard(db());

$salma=$g->repair([
 'item'=>'Miniature Princess Salma','item_key'=>'miniature-princess-salma',
 'raw_segment'=>'SALMA/ WHISPERS/ ZHED/ ANTON/',
 '_message'=>'WTS EL TONICS~~ QUEEN SALMA/ WHISPERS/ ZHED/ ANTON/',
 'quality_status'=>'review','quality_reason'=>'miniature_variant_unresolved'
]);
c19(($salma['item']??'')==='Everlasting Princess Salma Tonic','EL Salma tonic context',$salma['item']??null);

$kuuna=$g->repair([
 'item'=>'Miniature Kuunavang','item_key'=>'miniature-kuunavang',
 'raw_segment'=>'kuuna 50e','_message'=>'wts blessings of war 2:1e / el kuuna 50e',
 'quality_status'=>'review','quality_reason'=>'miniature_variant_unresolved'
]);
c19(($kuuna['item']??'')==='Everlasting Kuunavang Tonic','EL Kuuna tonic context',$kuuna['item']??null);

$rock=$g->repair([
 'item'=>'Rock Stack!','item_key'=>'rock-stack','raw_segment'=>'Rock Stack!',
 'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved'
]);
c19(($rock['item_key']??'')==='market-rock-candy-stack','Rock Stack market identity',$rock['item_key']??null);

$arm=$g->repair([
 'item'=>'ARMBRACESS full t','item_key'=>'armbracess-full-t','raw_segment'=>'ARMBRACESS 40e/Each full t',
 'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved'
]);
c19(($arm['item']??'')==='Armbrace of Truth','Armbracess canonical',$arm['item']??null);

$naga=$g->repair([
 'item'=>'naga pelts','item_key'=>'naga-pelts','raw_segment'=>'2 naga pelts',
 'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved'
]);
c19(($naga['item']??'')==='Naga Pelt','Naga Pelt canonical',$naga['item']??null);

$wand=$g->repair([
 'item'=>'Staff Wrapping of Energy Storage','item_key'=>'staff-wrapping-of-energy-storage',
 'raw_segment'=>'Wand Wrappings:','quality_status'=>'review','quality_reason'=>'low_confidence'
]);
c19(($wand['quality_reason']??'')==='strict_catalog_generic','Wand Wrappings header blocked',$wand['quality_reason']??null);

$sem=file_get_contents($root.'/app/Parser/SemanticNormalizer.php');
c19(str_contains((string)$sem,'LITTYWATCH_PHASE7E19_EL_TONIC_CONTEXT'),'EL tonic marker aanwezig');
c19(str_contains((string)$sem,'LITTYWATCH_PHASE7E19_DOA_GEMS_NO_TITAN'),'DoA gems no-titan marker aanwezig');

$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
c19(str_contains((string)$writer,'LITTYWATCH_PHASE7E19_PREINSERT_CONTEXT'),'writer 7E.19 marker aanwezig');

echo PHP_EOL;
if($fail){echo "Phase 7E.19 smoke-test: {$fail} fout(en).\n";exit(1);}
echo "Phase 7E.19 smoke-test volledig OK.\n";
echo "Daarna live-market reset voor zuivere meting; geen reparse-all.\n";
