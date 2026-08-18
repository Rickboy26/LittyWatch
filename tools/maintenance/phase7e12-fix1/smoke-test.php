<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$fail = 0;
function c12f1(bool $ok, string $label): void {
    global $fail;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) $fail++;
}

$st = db()->query("SELECT name,active FROM kb_items WHERE key='alcohol-point' LIMIT 1");
$r = $st->fetch(PDO::FETCH_ASSOC);

c12f1($r !== false, 'Alcohol Point KB item bestaat');
c12f1(($r['name'] ?? '') === 'Alcohol Point', 'canonical naam Alcohol Point');
c12f1((int)($r['active'] ?? 0) === 1, 'Alcohol Point actief');

foreach (['alc stacks','alc stack','1pt alc','1point alch','alcohol points'] as $alias) {
    $norm = mb_strtolower($alias);
    $norm = preg_replace('/[^a-z0-9]+/u',' ',$norm) ?? $norm;
    $norm = trim(preg_replace('/\s+/u',' ',$norm) ?? $norm);

    $st = db()->prepare("
        SELECT COUNT(*)
        FROM kb_aliases
        WHERE item_key='alcohol-point'
          AND normalized_alias=?
    ");
    $st->execute([$norm]);
    c12f1((int)$st->fetchColumn() >= 1, 'alias '.$alias);
}

$gate = (new \LittyWatch\Market\StrictCatalogGate(db()))
    ->inspect('Alcohol Point','alcohol-point');

c12f1((bool)($gate['allowed'] ?? false), 'StrictCatalogGate accepteert Alcohol Point');

echo PHP_EOL;
if ($fail) {
    echo "Phase 7E.12 FIX1 smoke-test: {$fail} fout(en).\n";
    exit(1);
}

echo "Phase 7E.12 FIX1 smoke-test volledig OK.\n";
echo "Daarna opnieuw live-market reset voor zuivere meting; geen reparse-all.\n";
