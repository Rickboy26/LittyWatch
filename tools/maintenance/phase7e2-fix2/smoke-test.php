<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

use LittyWatch\Parser\ParserEngine;

$engine = new ParserEngine();

$tests = [
    ['WTS Unded Kuuna 250a', 'Miniature Kuunavang', 'undedicated', true],
    ['WTS Ded Kuuna 20a', 'Miniature Kuunavang', 'dedicated', true],
    ['WTS Unded Rift Warden 25a', 'Miniature Rift Warden', 'undedicated', true],
    ['WTS Ded Rift Warden 5e', 'Miniature Rift Warden', 'dedicated', true],
    ['WTS Unded Rift War 25a', 'Miniature Rift Warden', 'undedicated', true],
    ['WTS unded mini dhuum 45a', 'Miniature Dhuum', 'undedicated', true],
    ['WTS Kuuna 250a', 'Miniature Kuunavang', null, false],
];

$fail = 0;
echo "Phase 7E.2 FIX2 ParserEngine checks\n";

foreach ($tests as [$text, $expectedItem, $expectedDed, $shouldAccept]) {
    $offers = $engine->parse($text);
    $found = null;
    foreach ($offers as $offer) {
        if ($offer->item === $expectedItem) {
            $found = $offer;
            break;
        }
    }

    if ($found === null) {
        printf("%-32s => %-26s FAIL (niet gevonden)\n", $text, $expectedItem);
        $fail++;
        continue;
    }

    $ded = $found->modifiers['dedication'] ?? $found->relevantProperties['dedication'] ?? null;
    $hasExpectedDed = $expectedDed === null ? $ded === null : $ded === $expectedDed;
    $accepted = $found->status === 'accepted' && $found->reason === 'catalog_match';
    $acceptOk = $shouldAccept ? $accepted : !$accepted;

    printf(
        "%-32s => %-26s ded=%-12s status=%-9s reason=%-30s %s\n",
        $text,
        $found->item,
        $ded ?? '-',
        $found->status,
        $found->reason,
        ($hasExpectedDed && $acceptOk) ? 'OK' : 'FAIL'
    );

    if (!$hasExpectedDed || !$acceptOk) $fail++;
}

if ($fail) {
    fwrite(STDERR, "\nPhase 7E.2 FIX2 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.2 FIX2 smoke-test: OK\n";
