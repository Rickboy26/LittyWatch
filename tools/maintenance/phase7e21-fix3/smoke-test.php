<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$file = $root . '/app/Market/Phase7E21AcceptedSafetyGuard.php';

if (!is_file($file)) {
    echo "FAIL guard file ontbreekt\n";
    exit(1);
}

require_once $file;

$g = new \LittyWatch\Market\Phase7E21AcceptedSafetyGuard();

$cases = [
    [
        'label' => 'r8 Tact => Tactics',
        'row' => [
            'item' => 'Celestial Shield',
            'item_key' => 'celestial-shield',
            'requirement' => 8,
            'attribute_key' => 'fire_magic',
            'attribute_name' => 'Fire Magic',
            'raw_segment' => 'Celestial Shield r8 Tact +10 Fire/-2we',
            'quality_status' => 'accepted',
            'quality_reason' => 'catalog_match',
        ],
        'expected' => 'tactics',
    ],
    [
        'label' => 'q9 str => Strength',
        'row' => [
            'item' => 'Shadow Shield',
            'item_key' => 'shadow-shield',
            'requirement' => 9,
            'attribute_key' => 'fire_magic',
            'attribute_name' => 'Fire Magic',
            'raw_segment' => 'q9 str Shadow Shield +1 Fire Magic 20%',
            'quality_status' => 'accepted',
            'quality_reason' => 'catalog_match',
        ],
        'expected' => 'strength',
    ],
    [
        'label' => 'q10 command => Command',
        'row' => [
            'item' => 'Oppressor Shield',
            'item_key' => 'oppressor-shield',
            'requirement' => 10,
            'attribute_key' => 'command',
            'attribute_name' => 'Command',
            'raw_segment' => 'Oppressor Shield q10 Command',
            'quality_status' => 'accepted',
            'quality_reason' => 'catalog_match',
        ],
        'expected' => 'command',
    ],
];

$fail = 0;

foreach ($cases as $case) {
    $out = $g->repair($case['row']);
    $actual = $out['attribute_key'] ?? null;
    $ok = $actual === $case['expected'];

    echo ($ok ? 'OK   ' : 'FAIL ') . $case['label'];

    if (!$ok) {
        echo ' [actual=' . ($actual ?? '-') . ']';
        $fail++;
    }

    echo PHP_EOL;
}

if ($fail) {
    echo PHP_EOL . "Phase 7E.21 FIX3 smoke-test: {$fail} fout(en)." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "Phase 7E.21 FIX3 smoke-test volledig OK." . PHP_EOL;
