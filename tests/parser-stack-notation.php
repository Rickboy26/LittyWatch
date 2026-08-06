<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$cleaner = new \LittyWatch\Parser\TradeNotationCleaner();

$cases = [
    'Rez /stk' => 'Rez',
    'Rez/stk' => 'Rez',
    'Rez per stack' => 'Rez',
    'Rez stack' => 'Rez',
    'Rez [x250]' => 'Rez',
    'Rez x250' => 'Rez',
    'Black Dye /each' => 'Black Dye',
];

$failed = [];
foreach ($cases as $input => $expected) {
    $actual = $cleaner->cleanItemCandidate($input);
    if ($actual !== $expected) {
        $failed[] = compact('input', 'expected', 'actual');
    }
}

$offers = parseOffers('WTS Rez 4e/stk');
$first = $offers[0] ?? null;
if (!$first || ($first['item'] ?? '') !== 'Rez' || ($first['basis'] ?? '') !== 'stack' || (float)($first['quantity'] ?? 0) !== 250.0) {
    $failed[] = [
        'input' => 'WTS Rez 4e/stk',
        'expected' => ['item' => 'Rez', 'basis' => 'stack', 'quantity' => 250],
        'actual' => $first,
    ];
}

echo json_encode([
    'ok' => $failed === [],
    'cases' => count($cases) + 1,
    'failed' => $failed,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failed === [] ? 0 : 1);
