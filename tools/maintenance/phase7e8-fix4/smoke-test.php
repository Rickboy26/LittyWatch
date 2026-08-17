<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

use LittyWatch\Market\Phase7E8NamedCollisionGuard;

$fail = 0;
function ck4(bool $ok, string $label, mixed $actual=null): void {
    global $fail;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label;
    if (!$ok && $actual !== null) echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
    echo PHP_EOL;
    if (!$ok) $fail++;
}

$g = new Phase7E8NamedCollisionGuard(db());

$kazhad = $g->repair([
    'item'=>'Miniature Kazhad Dhuum',
    'item_key'=>'miniature-kazhad-dhuum',
    'market_key'=>'miniature-kazhad-dhuum',
    'raw_segment'=>"sharp pointy stick /kazhad's fortune 5a/ea",
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.7,
]);

ck4(($kazhad['item'] ?? '') === "Kazhad's Fortune",
    "kazhad's fortune normaliseert exact naar Kazhad's Fortune",
    $kazhad['item'] ?? null);

ck4(($kazhad['item_key'] ?? '') === 'kazhad-s-fortune',
    "Kazhad's Fortune slug heeft geen dubbele -s",
    $kazhad['item_key'] ?? null);

ck4(($kazhad['quality_reason'] ?? '') === 'catalog_first_unresolved',
    "Kazhad's Fortune blijft veilig unresolved zolang KB-item ontbreekt",
    $kazhad['quality_reason'] ?? null);

$code = file_get_contents($root . '/app/Market/Phase7E8NamedCollisionGuard.php');
ck4(str_contains((string)$code, 'LITTYWATCH_PHASE7E8_FIX4_POSSESSIVE_FORTUNE'),
    'FIX4 marker aanwezig');

echo PHP_EOL;
if ($fail) {
    echo "Phase 7E.8 FIX4 smoke-test: {$fail} fout(en).\n";
    exit(1);
}

echo "Phase 7E.8 FIX4 smoke-test volledig OK.\n";
echo "Voor zuivere live meting: reset live market en laat alleen collector-data binnenkomen.\n";
