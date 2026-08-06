<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$failed = [];
$engine = new \LittyWatch\Parser\ParserEngine(
    new \LittyWatch\Parser\Catalog(dirname(__DIR__) . '/app/Data')
);

$cases = [
    'WTT Tengu flare 1:1 War supplies' => [
        'item' => 'Tengu Support Flare',
        'target' => 'War Supplies',
        'give' => 1.0,
        'receive' => 1.0,
    ],
    'WTT Tengu flare 2:1 Ghastly' => [
        'item' => 'Tengu Support Flare',
        'target' => 'Ghastly Summoning Stone',
        'give' => 2.0,
        'receive' => 1.0,
    ],
];

foreach ($cases as $message => $expected) {
    $legacy = parseOffers($message);
    $first = $legacy[0] ?? null;

    if (
        !$first
        || ($first['type'] ?? '') !== 'trade'
        || ($first['item'] ?? '') !== $expected['item']
        || ($first['exchange_item'] ?? '') !== $expected['target']
        || (float)($first['exchange_give_quantity'] ?? 0) !== $expected['give']
        || (float)($first['exchange_receive_quantity'] ?? 0) !== $expected['receive']
        || ($first['basis'] ?? '') !== 'barter'
    ) {
        $failed[] = ['message' => $message, 'parser' => 'legacy', 'actual' => $first];
    }

    $structured = $engine->parse($message);
    $parsed = $structured[0] ?? null;

    if (
        !$parsed
        || $parsed->tradeType !== 'trade'
        || $parsed->price->basis !== 'barter'
        || ($parsed->exchange['target_item'] ?? '') !== $expected['target']
        || (float)($parsed->exchange['give_quantity'] ?? 0) !== $expected['give']
        || (float)($parsed->exchange['receive_quantity'] ?? 0) !== $expected['receive']
    ) {
        $failed[] = [
            'message' => $message,
            'parser' => 'structured',
            'actual' => $parsed?->toArray(),
        ];
    }
}

echo json_encode([
    'ok' => $failed === [],
    'cases' => count($cases) * 2,
    'failed' => $failed,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failed === [] ? 0 : 1);
