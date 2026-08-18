<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$fail=0;
function c9f(bool $ok,string $label,mixed $actual=null):void{
    global $fail;
    echo ($ok?'OK   ':'FAIL ').$label;
    if(!$ok&&$actual!==null)echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
    echo PHP_EOL;
    if(!$ok)$fail++;
}

$g=new \LittyWatch\Market\Phase7E9LiveCleanupGuard(db());

$kuuna=$g->repair([
    'item'=>'Miniature Kuunavang',
    'item_key'=>'kuuna',
    'market_key'=>'kuuna',
    'raw_segment'=>'Miniature Kuunavang (UNOPENED CE BOX) 500a',
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.8,
]);

c9f(($kuuna['item_key']??'')==='miniature-kuunavang',
    'Kuunavang review key canonicaliseert exact',
    $kuuna['item_key']??null);

c9f(($kuuna['quality_reason']??'')==='miniature_variant_unresolved',
    'dedication policy blijft unresolved',
    $kuuna['quality_reason']??null);

$sem=file_get_contents($root.'/app/Parser/SemanticNormalizer.php');
c9f(str_contains((string)$sem,'LITTYWATCH_PHASE7E9_EL_GHOSTLY_PRIEST'),'EL Ghostly Priest marker aanwezig');
c9f(str_contains((string)$sem,'Everlasting Ghostly Priest Tonic'),'EL tonic replacement aanwezig');
c9f(str_contains((string)$sem,'LITTYWATCH_PHASE7E9_REGULAR_TOME_LIST'),'regular tome-list marker aanwezig');
c9f(str_contains((string)$sem,'Dervish Tome'),'Dervish tome expansion aanwezig');
c9f(str_contains((string)$sem,'Ritualist Tome'),'Ritualist tome expansion aanwezig');

$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
c9f(str_contains((string)$writer,'LITTYWATCH_PHASE7E9_PREINSERT_CLEANUP'),'writer preinsert cleanup marker aanwezig');

echo PHP_EOL;
if($fail){
    echo "Phase 7E.9 FIX1 smoke-test: {$fail} fout(en).\n";
    exit(1);
}
echo "Phase 7E.9 FIX1 smoke-test volledig OK.\n";
echo "Daarna: reset live market voor zuivere meting; geen reparse-all.\n";
