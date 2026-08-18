<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$fail = 0;
function ck92(bool $ok, string $label): void {
    global $fail;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) $fail++;
}

$code = file_get_contents($root . '/app/Parser/SemanticNormalizer.php');

ck92(str_contains((string)$code, 'LITTYWATCH_PHASE7E9_FIX2_GHOSTLY_PRIEST_TONIC_GUARD'),
    'FIX2 Ghostly Priest tonic guard marker aanwezig');

ck92(str_contains((string)$code, '(?<!Everlasting )'),
    'Everlasting negatieve context guard aanwezig');

ck92(str_contains((string)$code, '(?!\s+tonic\b)'),
    'Tonic negatieve context guard aanwezig');

ck92(str_contains((string)$code, 'Miniature Ghostly Priest'),
    'bare Ghostly Priest miniature mapping blijft aanwezig');

ck92(str_contains((string)$code, 'unded(?:icated)?|ded(?:icated)?'),
    'ded/unded Ghostly Priest mapping blijft aanwezig');

echo PHP_EOL;
if ($fail) {
    echo "Phase 7E.9 FIX2 smoke-test: {$fail} fout(en).\n";
    exit(1);
}

echo "Phase 7E.9 FIX2 smoke-test volledig OK.\n";
echo "Daarna live-market reset voor zuivere meting; geen reparse-all.\n";
