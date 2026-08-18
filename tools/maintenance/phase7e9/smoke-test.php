<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

use LittyWatch\Market\Phase7E9LiveCleanupGuard;

$fail=0;
function c9(bool $ok,string $label,mixed $actual=null):void{
    global $fail;
    echo ($ok?'OK   ':'FAIL ').$label;
    if(!$ok&&$actual!==null)echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
    echo PHP_EOL;
    if(!$ok)$fail++;
}

$g=new Phase7E9LiveCleanupGuard(db());

$kuuna=$g->repair([
    'item'=>'Miniature Kuunavang',
    'item_key'=>'kuuna',
    'market_key'=>'kuuna',
    'raw_segment'=>'Miniature Kuunavang (UNOPENED CE BOX) 500a',
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.8,
]);
c9(($kuuna['item_key']??'')!=='kuuna','Kuunavang review key canonicaliseert',$kuuna['item_key']??null);
c9(($kuuna['quality_reason']??'')==='miniature_variant_unresolved','dedication policy blijft unresolved',$kuuna['quality_reason']??null);

$sweet=$g->repair([
    'item'=>'sweet',
    'item_key'=>'sweet',
    'raw_segment'=>'sweet 3e/st',
    'quality_status'=>'review',
    'quality_reason'=>'catalog_first_unresolved',
    'confidence'=>0.7,
]);
c9(($sweet['quality_status']??'')==='rejected','sweet wordt rejected',$sweet['quality_status']??null);
c9(($sweet['quality_reason']??'')==='collection_or_market_request','sweet reason veilig',$sweet['quality_reason']??null);

$staff=$g->repair([
    'item'=>'Staff',
    'item_key'=>'staff',
    'raw_segment'=>'Staff',
    'quality_status'=>'review',
    'quality_reason'=>'low_confidence',
    'confidence'=>0.6,
]);
c9(($staff['quality_reason']??'')==='strict_catalog_generic','bare Staff low-confidence wordt strict generic',$staff['quality_reason']??null);

$sem=file_get_contents($root.'/app/Parser/SemanticNormalizer.php');
c9(str_contains((string)$sem,'LITTYWATCH_PHASE7E9_EL_GHOSTLY_PRIEST'),'EL Ghostly Priest marker aanwezig');
c9(str_contains((string)$sem,'Everlasting Ghostly Priest Tonic'),'EL tonic replacement aanwezig');
c9(str_contains((string)$sem,'LITTYWATCH_PHASE7E9_REGULAR_TOME_LIST'),'regular tome-list marker aanwezig');
c9(str_contains((string)$sem,'Dervish Tome'),'Dervish tome expansion aanwezig');
c9(str_contains((string)$sem,'Ritualist Tome'),'Ritualist tome expansion aanwezig');

$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
c9(str_contains((string)$writer,'LITTYWATCH_PHASE7E9_PREINSERT_CLEANUP'),'writer preinsert cleanup marker aanwezig');

echo PHP_EOL;
if($fail){
    echo "Phase 7E.9 smoke-test: {$fail} fout(en).\n";
    exit(1);
}
echo "Phase 7E.9 smoke-test volledig OK.\n";
echo "Daarna: reset live market voor een zuivere meting; geen reparse-all.\n";
