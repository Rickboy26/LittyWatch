<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

use LittyWatch\Market\Phase7E8NamedCollisionGuard;

$fail=0;
function ck(bool $ok,string $label,mixed $actual=null):void{
    global $fail;
    echo ($ok?'OK   ':'FAIL ').$label;
    if(!$ok&&$actual!==null)echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
    echo PHP_EOL;
    if(!$ok)$fail++;
}

$g=new Phase7E8NamedCollisionGuard(db());

$madruk=$g->repair([
    'item'=>'Miniature Madruk Dhuum',
    'item_key'=>'miniature-madruk-dhuum',
    'market_key'=>'miniature-madruk-dhuum',
    'raw_segment'=>"Madruk's Prophecy",
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.7,
]);
ck(($madruk['item']??'')==="Madruk's Prophecy","Madruk's Prophecy herstelt naar named item",$madruk['item']??null);
ck(($madruk['quality_reason']??'')==='catalog_match',"Madruk's Prophecy is catalog_match",$madruk['quality_reason']??null);

$kazhad=$g->repair([
    'item'=>'Miniature Kazhad Dhuum',
    'item_key'=>'miniature-kazhad-dhuum',
    'market_key'=>'miniature-kazhad-dhuum',
    'raw_segment'=>"sharp pointy stick /kazhad's fortune 5a/ea",
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.7,
]);
ck(($kazhad['item']??'')!=='Miniature Kazhad Dhuum',"Kazhad's Fortune wordt nooit false miniature",$kazhad['item']??null);
ck(in_array(($kazhad['quality_reason']??''),['catalog_match','catalog_first_unresolved'],true),"Kazhad Fortune veilige status",$kazhad['quality_reason']??null);

$semantic=file_get_contents($root.'/app/Parser/SemanticNormalizer.php');
ck(str_contains((string)$semantic,'LITTYWATCH_PHASE7E8_FIX1_MADRUK_GUARD'),'Madruk semantic guard marker aanwezig');

$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
ck(str_contains((string)$writer,'LITTYWATCH_PHASE7E8_FIX1_NAMED_COLLISION'),'named collision writer marker aanwezig');
ck(str_contains((string)$writer,'LITTYWATCH_PHASE7E8_FIX1_GENERIC_MINI_SUPPRESS'),'generic miniature suppress marker aanwezig');

echo PHP_EOL;
if($fail){
    echo "Phase 7E.8 FIX1 smoke-test: {$fail} fout(en).\n";
    exit(1);
}
echo "Phase 7E.8 FIX1 smoke-test volledig OK.\n";
echo "Voor zuivere meting: reset daarna live market opnieuw en laat alleen collector-data binnenkomen.\n";
