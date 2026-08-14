<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$catalog = new Catalog($root . '/app/Data', db());
$engine = new ParserEngine($catalog);

$tests = [
    ['WTS Unded Kuuna 250a', 'Miniature Kuunavang', 'undedicated', 'accepted', 'catalog_match'],
    ['WTS Ded Kuuna 20a', 'Miniature Kuunavang', 'dedicated', 'accepted', 'catalog_match'],
    ['WTS Kuuna 250a', 'Miniature Kuunavang', null, 'review', 'miniature_variant_unresolved'],

    ['WTS Unded Rift Warden 25a', 'Miniature Rift Warden', 'undedicated', 'accepted', 'catalog_match'],
    ['WTS Ded Rift Warden 5e', 'Miniature Rift Warden', 'dedicated', 'accepted', 'catalog_match'],
    ['WTS Rift Warden 25a', 'Miniature Rift Warden', null, 'review', 'miniature_variant_unresolved'],

    ['WTS unded mini dhuum 45a', 'Miniature Dhuum', 'undedicated', 'accepted', 'catalog_match'],
    ['WTS mini dhuum 45a', 'Miniature Dhuum', null, 'review', 'miniature_variant_unresolved'],

    ['WTS Ghero 5a', 'Miniature Ghostly Hero', null, 'review', 'miniature_variant_unresolved'],
    ['WTS Unded Ghero 5a', 'Miniature Ghostly Hero', 'undedicated', 'accepted', 'catalog_match'],
];

$fail = 0;

echo "Phase 7E.2 FIX3 bare miniature variant gate\n";

foreach ($tests as [$text, $item, $dedExpected, $statusExpected, $reasonExpected]) {
    $found = null;
    foreach ($engine->parse($text) as $offer) {
        if ($offer->item === $item) {
            $found = $offer;
            break;
        }
    }

    if ($found === null) {
        printf("%-30s => %-26s FAIL (niet gevonden)\n", $text, $item);
        $fail++;
        continue;
    }

    $ded = $found->modifiers['dedication']
        ?? $found->relevantProperties['dedication']
        ?? null;

    $ok =
        $ded === $dedExpected
        && $found->status === $statusExpected
        && $found->reason === $reasonExpected;

    printf(
        "%-30s => %-26s ded=%-12s status=%-8s reason=%-30s %s\n",
        $text,
        $found->item,
        $ded ?? '-',
        $found->status,
        $found->reason,
        $ok ? 'OK' : 'FAIL'
    );

    if (!$ok) $fail++;
}

// Regression checks: non-miniature FIX1 identities must remain untouched.
$regressions = [
    ['WTB Dhuum Scythe', "Dhuum's Soul Reaper"],
    ['WTS DSR 25a', "Dhuum's Soul Reaper"],
    ['WTS Kath Set 30a', 'Kathandrax Hammer'],
];

echo "\nRegression checks\n";
foreach ($regressions as [$text, $expected]) {
    $names = array_map(static fn($o) => $o->item, $engine->parse($text));
    $ok = in_array($expected, $names, true);
    printf("%-30s => %-26s %s\n", $text, implode(', ', $names) ?: '(geen)', $ok ? 'OK' : 'FAIL');
    if (!$ok) $fail++;
}

if ($fail > 0) {
    fwrite(STDERR, "\nPhase 7E.2 FIX3 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.2 FIX3 smoke-test: OK\n";
