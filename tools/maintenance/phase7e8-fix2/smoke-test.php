<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

use LittyWatch\Market\Phase7E8NamedCollisionGuard;

$fail=0;
function ck2(bool $ok,string $label,mixed $actual=null):void{
    global $fail;
    echo ($ok?'OK   ':'FAIL ').$label;
    if(!$ok&&$actual!==null)echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
    echo PHP_EOL;
    if(!$ok)$fail++;
}

$g=new Phase7E8NamedCollisionGuard(db());

$kazhad=$g->repair([
    'item'=>'Miniature Kazhad Dhuum',
    'item_key'=>'miniature-kazhad-dhuum',
    'market_key'=>'miniature-kazhad-dhuum',
    'raw_segment'=>"sharp pointy stick /kazhad's fortune 5a/ea",
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.7,
]);

ck2(($kazhad['item']??'')!=='Miniature Kazhad Dhuum',
    "lowercase kazhad's fortune wordt nooit false miniature",
    $kazhad['item']??null);

ck2(in_array(($kazhad['quality_reason']??''),['catalog_match','catalog_first_unresolved'],true),
    "Kazhad Fortune veilige status",
    $kazhad['quality_reason']??null);

$madruk=$g->repair([
    'item'=>'Miniature Madruk Dhuum',
    'item_key'=>'miniature-madruk-dhuum',
    'market_key'=>'miniature-madruk-dhuum',
    'raw_segment'=>"Madruk's Prophecy",
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.7,
]);

ck2(($madruk['item']??'')==="Madruk's Prophecy",
    "Madruk's Prophecy blijft named item",
    $madruk['item']??null);

ck2(($madruk['quality_reason']??'')==='catalog_match',
    "Madruk's Prophecy blijft catalog_match",
    $madruk['quality_reason']??null);

$code=file_get_contents($root.'/app/Market/Phase7E8NamedCollisionGuard.php');
ck2(str_contains((string)$code,'LITTYWATCH_PHASE7E8_FIX2_FORTUNE_CASE'),
    'FIX2 Fortune case marker aanwezig');

echo PHP_EOL;
if($fail){
    echo "Phase 7E.8 FIX2 smoke-test: {$fail} fout(en).\n";
    exit(1);
}
echo "Phase 7E.8 FIX2 smoke-test volledig OK.\n";
