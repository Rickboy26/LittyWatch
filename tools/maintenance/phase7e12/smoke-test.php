<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$fail = 0;
function c12(bool $ok, string $label, mixed $actual=null): void {
    global $fail;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label;
    if (!$ok && $actual !== null) {
        echo ' [actual=' . (is_scalar($actual) ? (string)$actual : json_encode($actual)) . ']';
    }
    echo PHP_EOL;
    if (!$ok) $fail++;
}

$g = new \LittyWatch\Market\Phase7E12ResidualGuard(db());

$alc = $g->repair([
    'item'=>'alc stacks',
    'item_key'=>'alc-stacks',
    'raw_segment'=>'alc stacks 2e ea',
    'quality_status'=>'review',
    'quality_reason'=>'catalog_first_unresolved',
    'confidence'=>0.6,
]);
c12(($alc['item']??'')==='Alcohol Point','alc stacks => Alcohol Point',$alc['item']??null);
c12(($alc['item_key']??'')==='alcohol-point','Alcohol Point canonical key',$alc['item_key']??null);
c12(($alc['quality_reason']??'')==='catalog_match','Alcohol Point catalog_match',$alc['quality_reason']??null);

$unid = $g->repair([
    'item'=>'unided gold',
    'item_key'=>'unided-gold',
    'raw_segment'=>'unided gold 1.1k each',
    'quality_status'=>'review',
    'quality_reason'=>'catalog_first_unresolved',
    'confidence'=>0.6,
]);
c12(($unid['item']??'')==='Unidentified Gold','unided gold => Unidentified Gold',$unid['item']??null);
c12(($unid['item_key']??'')==='unidentified-gold','Unidentified Gold canonical key',$unid['item_key']??null);

$noise = $g->repair([
    'item'=>'No idea',
    'item_key'=>'no-idea',
    'raw_segment'=>'No idea',
    'quality_status'=>'review',
    'quality_reason'=>'catalog_first_unresolved',
    'confidence'=>0.5,
]);
c12(($noise['quality_reason']??'')==='service_or_noise','No idea => service_or_noise',$noise['quality_reason']??null);

$dragon = $g->repair([
    'item'=>'Miniature Celestial Dragon',
    'item_key'=>'miniature-celestial-dragon',
    'raw_segment'=>'sunspear Q11,12,Dragon Staffof Fortitude Air q9 os,exclusive',
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.6,
]);
c12(($dragon['quality_reason']??'')==='strict_catalog_generic','Dragon Staff false miniature rejected',$dragon['quality_reason']??null);

$egg = $g->repair([
    'item'=>'Golden Egg',
    'item_key'=>'golden-egg',
    'raw_segment'=>'Eggs Slice of',
    'quality_status'=>'review',
    'quality_reason'=>'low_confidence',
    'confidence'=>0.5,
]);
c12(($egg['quality_reason']??'')==='service_or_noise','Eggs Slice of rejected',$egg['quality_reason']??null);

$elite = $g->repair([
    'item'=>'Elite Tome',
    'item_key'=>'elite-tome',
    'raw_segment'=>'Elite Tome 3e/each',
    'quality_status'=>'review',
    'quality_reason'=>'insufficient_item_identity',
    'confidence'=>0.5,
]);
c12(($elite['quality_reason']??'')==='insufficient_item_identity','Elite Tome policy unchanged',$elite['quality_reason']??null);

$writer = file_get_contents($root . '/app/Market/StructuredOfferWriter.php');
c12(str_contains((string)$writer,'LITTYWATCH_PHASE7E12_PREINSERT_RESIDUAL'),'writer 7E.12 marker aanwezig');

echo PHP_EOL;
if ($fail) {
    echo "Phase 7E.12 smoke-test: {$fail} fout(en).\n";
    exit(1);
}
echo "Phase 7E.12 smoke-test volledig OK.\n";
echo "Daarna live-market reset voor zuivere meting; geen reparse-all.\n";
