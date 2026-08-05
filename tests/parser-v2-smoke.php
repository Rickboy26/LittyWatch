<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

$cases = [
    ['WTS q8 Colossal Scimitar 200a', 'Colossal Scimitar', 'sell'],
    ['WTB Q13 EBlade 4a', 'Eternal Blade', 'buy'],
    ['WTS ObsiEdge / EternalBlade / VoltaicSpear all unidentified package 22a', 'Bundle:', 'sell'],
    ['WTS 250 GOTT 30a', 'Gift of the Traveler', 'sell'],
];

$failed = 0;
foreach ($cases as [$message, $expectedItem, $expectedType]) {
    $offers = parserV2()->parse($message);
    $first = $offers[0] ?? null;
    $ok = $first !== null
        && str_contains($first->item, $expectedItem)
        && $first->tradeType === $expectedType;
    echo ($ok ? '[OK] ' : '[FAIL] ') . $message . PHP_EOL;
    if (!$ok) $failed++;
}
exit($failed > 0 ? 1 : 0);
