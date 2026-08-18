<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$fail=0;
function c18(bool $ok,string $label,mixed $actual=null):void{
    global $fail;
    echo ($ok?'OK   ':'FAIL ').$label;
    if(!$ok&&$actual!==null)echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
    echo PHP_EOL;
    if(!$ok)$fail++;
}

$g=new \LittyWatch\Market\Phase7E18StructuralCleanupGuard(db());

$tail=$g->repair([
    'item'=>'. [x5 left]','item_key'=>'x5-left','raw_segment'=>'20e. [x5 left]',
    'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved','confidence'=>0.5
]);
c18(($tail['quality_status']??'')==='rejected','stock tail rejected',$tail['quality_reason']??null);

$alc=$g->repair([
    'item'=>'alcohol','item_key'=>'alcohol','raw_segment'=>'alcohol stk 2e',
    'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved'
]);
c18(($alc['item_key']??'')==='market-points-alcohol','alcohol stk => Alcohol Points',$alc['item_key']??null);

$claws=$g->repair([
    'item'=>'claws of bro','item_key'=>'claws-of-bro','raw_segment'=>'claws of bro',
    'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved'
]);
c18(($claws['item']??'')==='Claws of the Broodmother','claws of bro canonical',$claws['item']??null);

$prime=$g->repair([
    'item'=>'Primeval','item_key'=>'primeval','raw_segment'=>'Primeval',
    'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved'
]);
c18(($prime['item']??'')==='Primeval Armor Remnant','Primeval canonical',$prime['item']??null);

$deep=$g->repair([
    'item'=>'THE DEEP HM','item_key'=>'the-deep-hm','raw_segment'=>'THE DEEP HM 100e',
    'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved'
]);
c18(($deep['quality_reason']??'')==='service_or_noise','Deep HM rejected',$deep['quality_reason']??null);

$dragon=$g->repair([
    'item'=>'Miniature Celestial Dragon','item_key'=>'miniature-celestial-dragon',
    'raw_segment'=>'10 stacks Dragon Roots',
    'quality_status'=>'review','quality_reason'=>'miniature_variant_unresolved'
]);
c18(($dragon['item']??'')==='Dragon Root','Dragon Roots false miniature fixed',$dragon['item']??null);

$mallyx=$g->repair([
    'item'=>'Miniature Mallyx','item_key'=>'mallyx',
    'raw_segment'=>"Mallyx's Pepetuity",
    'quality_status'=>'review','quality_reason'=>'miniature_variant_unresolved'
]);
c18(($mallyx['quality_reason']??'')==='catalog_first_unresolved','Mallyx collision no longer miniature',$mallyx['quality_reason']??null);

$mod=$g->repair([
    'item'=>'(while Enchanted)','item_key'=>'while-enchanted',
    'raw_segment'=>'AnA / +45hp while enchanted /',
    'quality_status'=>'review','quality_reason'=>'low_confidence'
]);
c18(($mod['quality_reason']??'')==='modifier_fragment_unresolved','+45hp while enchanted rejected',$mod['quality_reason']??null);

$sharp=$g->repair([
    'item'=>'Sharp Pointy Stick','item_key'=>'sharp-pointy-stick',
    'raw_segment'=>'Sharp Pointy Stick',
    'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved'
]);
c18(($sharp['quality_reason']??'')==='catalog_first_unresolved','Sharp Pointy Stick deliberately unresolved');

$sem=file_get_contents($root.'/app/Parser/SemanticNormalizer.php');
c18(str_contains((string)$sem,'LITTYWATCH_PHASE7E18_HONEYCOMB_CUPCAKE_SPLIT'),'Honeycomb/Cupcake split marker aanwezig');

$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
c18(str_contains((string)$writer,'LITTYWATCH_PHASE7E18_PREINSERT_STRUCTURAL_CLEANUP'),'writer 7E.18 marker aanwezig');

echo PHP_EOL;
if($fail){
    echo "Phase 7E.18 smoke-test: {$fail} fout(en).\n";
    exit(1);
}
echo "Phase 7E.18 smoke-test volledig OK.\n";
echo "Daarna live-market reset voor zuivere meting; geen reparse-all.\n";
