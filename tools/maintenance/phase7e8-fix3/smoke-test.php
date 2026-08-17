<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$fail = 0;
function ck3(bool $ok, string $label): void {
    global $fail;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) $fail++;
}

$file = $root . '/app/Market/StructuredOfferWriter.php';
$code = file_get_contents($file);

ck3(str_contains((string)$code, 'LITTYWATCH_PHASE7E8_FIX3_PREINSERT_REVIEW_GUARD'),
    'unconditional review collision guard marker aanwezig');

ck3(str_contains((string)$code, 'LITTYWATCH_PHASE7E8_FIX3_GENERIC_MINI_PREINSERT'),
    'generic miniature pre-insert marker aanwezig');

$posReconcile = strpos((string)$code, '$r=$this->reconcileMiniatureVariant($r);');
$posGuard = strpos((string)$code, 'LITTYWATCH_PHASE7E8_FIX3_PREINSERT_REVIEW_GUARD');
$posAccepted = strpos((string)$code, "if(\$r['quality_status']==='accepted')");

ck3($posReconcile !== false && $posGuard !== false && $posAccepted !== false
    && $posReconcile < $posGuard && $posGuard < $posAccepted,
    'NamedCollisionGuard staat vóór accepted-only branch');

ck3(!str_contains((string)$code, 'LITTYWATCH_PHASE7E8_FIX1_NAMED_COLLISION'),
    'oude accepted-only named collision marker verwijderd');

ck3(!str_contains((string)$code, 'LITTYWATCH_PHASE7E8_FIX1_GENERIC_MINI_SUPPRESS'),
    'oude accepted-only generic mini suppress marker verwijderd');

// Verify the guard itself still handles the real live examples.
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

ck3(($kazhad['item'] ?? '') !== 'Miniature Kazhad Dhuum',
    "Kazhad's Fortune guard werkt op review row");

$madruk = $g->repair([
    'item'=>'Miniature Madruk Dhuum',
    'item_key'=>'miniature-madruk-dhuum',
    'market_key'=>'miniature-madruk-dhuum',
    'raw_segment'=>"Madruk's Prophecy",
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.7,
]);

ck3(($madruk['item'] ?? '') === "Madruk's Prophecy"
    && ($madruk['quality_reason'] ?? '') === 'catalog_match',
    "Madruk's Prophecy review row herstelt naar catalog_match");

echo PHP_EOL;
if ($fail) {
    echo "Phase 7E.8 FIX3 smoke-test: {$fail} fout(en).\n";
    exit(1);
}

echo "Phase 7E.8 FIX3 smoke-test volledig OK.\n";
echo "Voor zuivere live meting: reset live market, daarna NIET reparse-all.\n";
