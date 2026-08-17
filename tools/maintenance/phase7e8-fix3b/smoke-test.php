<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$fail = 0;
function chk(bool $ok, string $label): void {
    global $fail;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) $fail++;
}

$code = file_get_contents($root . '/app/Market/StructuredOfferWriter.php');

chk(str_contains((string)$code, 'LITTYWATCH_PHASE7E8_FIX3B_PREINSERT_REVIEW_GUARD'),
    'unconditional review guard marker aanwezig');

chk(str_contains((string)$code, 'LITTYWATCH_PHASE7E8_FIX3B_GENERIC_MINI_PREINSERT'),
    'generic miniature pre-insert marker aanwezig');

$reconcilePos = strpos((string)$code, '$r=$this->reconcileMiniatureVariant($r);');
$guardPos = strpos((string)$code, 'LITTYWATCH_PHASE7E8_FIX3B_PREINSERT_REVIEW_GUARD');
$acceptedPos = strpos((string)$code, "if(\$r['quality_status']==='accepted')");

chk($reconcilePos !== false && $guardPos !== false && $acceptedPos !== false
    && $reconcilePos < $guardPos && $guardPos < $acceptedPos,
    'review guard staat vóór accepted-only branch');

chk(!str_contains((string)$code, 'LITTYWATCH_PHASE7E8_FIX1_NAMED_COLLISION'),
    'oude accepted-only named guard verwijderd');

chk(!str_contains((string)$code, 'LITTYWATCH_PHASE7E8_FIX1_GENERIC_MINI_SUPPRESS'),
    'oude accepted-only generic mini suppress verwijderd');

$g = new \LittyWatch\Market\Phase7E8NamedCollisionGuard(db());

$kazhad = $g->repair([
    'item'=>'Miniature Kazhad Dhuum',
    'item_key'=>'miniature-kazhad-dhuum',
    'market_key'=>'miniature-kazhad-dhuum',
    'raw_segment'=>"sharp pointy stick /kazhad's fortune 5a/ea",
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.7,
]);

chk(($kazhad['item'] ?? '') !== 'Miniature Kazhad Dhuum',
    "Kazhad's Fortune review-row guard werkt");

$madruk = $g->repair([
    'item'=>'Miniature Madruk Dhuum',
    'item_key'=>'miniature-madruk-dhuum',
    'market_key'=>'miniature-madruk-dhuum',
    'raw_segment'=>"Madruk's Prophecy",
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.7,
]);

chk(($madruk['item'] ?? '') === "Madruk's Prophecy"
    && ($madruk['quality_reason'] ?? '') === 'catalog_match',
    "Madruk's Prophecy review-row guard werkt");

echo PHP_EOL;
if ($fail) {
    echo "Phase 7E.8 FIX3B smoke-test: {$fail} fout(en).\n";
    exit(1);
}

echo "Phase 7E.8 FIX3B smoke-test volledig OK.\n";
echo "Daarna live-market reset voor een zuivere meting; geen reparse-all.\n";
