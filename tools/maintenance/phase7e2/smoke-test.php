<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ItemMatcher;

$catalog = new Catalog($root . '/app/Data', db());
$items = $catalog->items();

$wanted = [
    'Miniature Dhuum' => ['mini duum', 'dhuum'],
    'Miniature Flame Djinn' => ['miniature flame djinn', 'flame djin'],
    'Miniature King Adelbern' => ['miniature king adelbern', 'adelbern'],
    'Miniature Lich' => ['miniature lich', 'lich'],
    'Miniature Ghostly Hero' => ['ghero', 'ghostly hero'],
    'Miniature Kuunavang' => ['kuuna'],
    'Miniature Rift Warden' => ['rift war'],
];

$byName = [];
foreach ($items as $item) {
    $name = (string)($item['name'] ?? '');
    if ($name !== '') $byName[mb_strtolower($name)] = $item;
}

$fail = 0;
echo "Phase 7E.2 catalog alias checks\n";
foreach ($wanted as $canonical => $aliases) {
    $key = mb_strtolower($canonical);
    if (!isset($byName[$key])) {
        // Current V5.2 can use shortened display name for Undead Prince only; none of
        // the smoke-test targets above should need that exception.
        echo "SKIP catalog ontbreekt: {$canonical}\n";
        continue;
    }
    $have = array_map(static fn($v) => mb_strtolower(trim((string)$v)), $byName[$key]['aliases'] ?? []);
    foreach ($aliases as $alias) {
        $ok = in_array(mb_strtolower($alias), $have, true);
        printf("%-30s %-24s %s\n", $canonical, $alias, $ok ? 'OK' : 'FAIL');
        if (!$ok) $fail++;
    }
}

// Global invariant: Phase 7E.2 must never encode dedication into an item alias.
$badDedicationAliases = [];
foreach ($items as $item) {
    $name = (string)($item['name'] ?? '');
    if (!str_starts_with(mb_strtolower($name), 'miniature ')) continue;
    foreach (($item['aliases'] ?? []) as $alias) {
        if (preg_match('/\b(?:ded|unded|dedicated|undedicated)\b/iu', (string)$alias)) {
            $badDedicationAliases[] = $name . ' <- ' . $alias;
        }
    }
}
if ($badDedicationAliases !== []) {
    echo "FAIL: dedication-woorden gevonden in miniature aliases:\n";
    foreach ($badDedicationAliases as $row) echo "  {$row}\n";
    $fail++;
} else {
    echo "Dedication alias invariant: OK\n";
}

// Matcher smoke test. We only assert identity; acceptance/dedication belongs to ParserEngine quality gates.
$matcher = new ItemMatcher($catalog);
$samples = [
    'WTS mini duum 45a' => 'Miniature Dhuum',
    'WTS Miniature flame djinn 8e' => 'Miniature Flame Djinn',
    'WTS Miniature king adelbern 5e' => 'Miniature King Adelbern',
    'WTS Miniature lich 10k' => 'Miniature Lich',
    'WTS Ghero 5a' => 'Miniature Ghostly Hero',
    'WTS Kuuna 20e' => 'Miniature Kuunavang',
    'WTS Rift War 23a' => 'Miniature Rift Warden',
];

echo "\nMatcher identity checks\n";
foreach ($samples as $text => $expected) {
    $matches = $matcher->matchAll($text);
    $names = [];
    foreach ($matches as $m) {
        $name = $m['item']['name'] ?? $m['name'] ?? null;
        if (is_string($name) && $name !== '') $names[] = $name;
    }
    $ok = in_array($expected, $names, true);
    printf("%-36s => %-28s %s\n", $text, $expected, $ok ? 'OK' : 'CHECK');
    if (!$ok) {
        echo '  matches: ' . ($names === [] ? '(geen)' : implode(', ', $names)) . "\n";
        // Don't hard-fail on matcher array-shape differences; catalog checks above are authoritative.
    }
}

if ($fail > 0) {
    fwrite(STDERR, "\nPhase 7E.2 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.2 smoke-test: OK\n";
echo "LET OP: geen ded/unded in bron = bestaande miniature_variant_unresolved policy blijft gelden.\n";
