<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$fail=0;
function ok20(bool $ok,string $label):void{
    global $fail;
    echo ($ok?'OK   ':'FAIL ').$label.PHP_EOL;
    if(!$ok)$fail++;
}

$file=$root.'/app/Market/Phase7E20ResidualSemanticsGuard.php';
ok20(is_file($file),'guard file bestaat');

if(is_file($file)){
    require_once $file;
}

ok20(class_exists(\LittyWatch\Market\Phase7E20ResidualSemanticsGuard::class),'guard class laadbaar');

$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
ok20(str_contains((string)$writer,'LITTYWATCH_PHASE7E20_PREINSERT_RESIDUAL'),'writer 7E.20 marker aanwezig');

$semantic=file_get_contents($root.'/app/Parser/SemanticNormalizer.php');
ok20(str_contains((string)$semantic,'LITTYWATCH_PHASE7E20_DOA_GEM_PRICE_SPLIT'),'semantic 7E.20 marker aanwezig');

if(class_exists(\LittyWatch\Market\Phase7E20ResidualSemanticsGuard::class)){
    $g=new \LittyWatch\Market\Phase7E20ResidualSemanticsGuard(db());

    $blue=$g->repair([
        'item'=>'bleu dyes',
        'item_key'=>'bleu-dyes',
        'raw_segment'=>'bleu dyes stack 50a',
        'quality_status'=>'review',
        'quality_reason'=>'catalog_first_unresolved'
    ]);
    ok20(($blue['item']??'')==='Blue Dye','bleu dyes => Blue Dye');

    $anc=$g->repair([
        'item'=>'Ancient Armor',
        'item_key'=>'ancient-armor',
        'raw_segment'=>'3 Ancient Armor',
        'quality_status'=>'review',
        'quality_reason'=>'catalog_first_unresolved'
    ]);
    ok20(($anc['item']??'')==='Ancient Armor Remnant','Ancient Armor canonical');

    $glad=$g->repair([
        'item'=>'Glad Boxes',
        'item_key'=>'glad-boxes',
        'raw_segment'=>'3 Glad Boxes',
        'quality_status'=>'review',
        'quality_reason'=>'catalog_first_unresolved'
    ]);
    ok20(($glad['item']??'')==="Gladiator's Zaishen Strongbox",'Glad Boxes canonical');
}

echo PHP_EOL;
if($fail){
    echo "Phase 7E.20 FIX1 smoke-test: {$fail} fout(en).".PHP_EOL;
    exit(1);
}
echo "Phase 7E.20 FIX1 smoke-test volledig OK.".PHP_EOL;
