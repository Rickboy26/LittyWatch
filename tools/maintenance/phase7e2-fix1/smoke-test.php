<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ItemMatcher;

$catalog = new Catalog($root . '/app/Data', db());
$items = $catalog->items();
$matcher = new ItemMatcher($catalog);

$byName = [];
foreach ($items as $item) {
    $name = mb_strtolower(trim((string)($item['name'] ?? '')));
    if ($name !== '') $byName[$name] = $item;
}

$fail = 0;

function aliasesFor(array $byName, string $canonical): array {
    $key = mb_strtolower($canonical);
    if (!isset($byName[$key])) return [];
    return array_values(array_unique(array_map(
        static fn($v) => mb_strtolower(trim((string)$v)),
        is_array($byName[$key]['aliases'] ?? null) ? $byName[$key]['aliases'] : []
    )));
}

function namesFor(ItemMatcher $matcher, string $text): array {
    $names = [];
    foreach ($matcher->matchAll($text) as $m) {
        $name = $m['item']['name'] ?? $m['name'] ?? $m['item'] ?? null;
        if (is_string($name) && $name !== '') $names[] = $name;
    }
    return array_values(array_unique($names));
}

echo "Phase 7E.2 FIX1 catalog checks\n";

$miniDhuumAliases = aliasesFor($byName, 'Miniature Dhuum');
$bareDhuum = in_array('dhuum', $miniDhuumAliases, true);
printf("%-46s %s\n", "Miniature Dhuum heeft GEEN kale 'dhuum' alias", !$bareDhuum ? 'OK' : 'FAIL');
if ($bareDhuum) $fail++;

$contextWanted = [
    'Miniature Dhuum' => ['unded mini dhuum', 'ded mini dhuum'],
    'Miniature Kuunavang' => ['unded kuuna', 'ded kuuna'],
    'Miniature Rift Warden' => ['unded rift warden', 'ded rift warden', 'unded rift war'],
];

foreach ($contextWanted as $canonical => $aliases) {
    $have = aliasesFor($byName, $canonical);
    if ($have === []) {
        echo "SKIP: catalog ontbreekt: {$canonical}\n";
        continue;
    }
    foreach ($aliases as $alias) {
        $ok = in_array(mb_strtolower($alias), $have, true);
        printf("%-28s %-24s %s\n", $canonical, $alias, $ok ? 'OK' : 'FAIL');
        if (!$ok) $fail++;
    }
}

$canonicalExtras = [
    "Dhuum's Soul Reaper" => ['dsr', 'dhuum scythe', 'dhuum soul reaper'],
    'Kathandrax Hammer' => ['kath hammer', 'kath set', 'kath'],
];

foreach ($canonicalExtras as $canonical => $aliases) {
    $have = aliasesFor($byName, $canonical);
    if ($have === []) {
        echo "FAIL: bestaande catalog canonical ontbreekt: {$canonical}\n";
        $fail++;
        continue;
    }
    foreach ($aliases as $alias) {
        $ok = in_array(mb_strtolower($alias), $have, true);
        printf("%-28s %-24s %s\n", $canonical, $alias, $ok ? 'OK' : 'FAIL');
        if (!$ok) $fail++;
    }
}

echo "\nMatcher conflict checks\n";
$tests = [
    'WTB Dhuum Scythe' => ["Dhuum's Soul Reaper", 'Miniature Dhuum'],
    'WTS DSR 25a' => ["Dhuum's Soul Reaper", 'Miniature Dhuum'],
    'WTS q9 DSR 25a' => ["Dhuum's Soul Reaper", 'Miniature Dhuum'],
    'WTS Kath Set 30a' => ['Kathandrax Hammer', 'Kath Set'],
    'WTS Kath Hammer 3e/ea' => ['Kathandrax Hammer', 'Kath Set'],
];

foreach ($tests as $text => [$mustHave, $mustNotHave]) {
    $names = namesFor($matcher, $text);
    $okHave = in_array($mustHave, $names, true);
    $okNot = !in_array($mustNotHave, $names, true);
    printf("%-30s => %-24s have=%s conflict=%s\n",
        $text,
        implode(', ', $names) ?: '(geen)',
        $okHave ? 'OK' : 'FAIL',
        $okNot ? 'OK' : 'FAIL'
    );
    if (!$okHave || !$okNot) $fail++;
}

echo "\nExplicit dedication context checks\n";
$dedTests = [
    'WTS Unded Kuuna 250a' => 'Miniature Kuunavang',
    'WTS Ded Kuuna 20a' => 'Miniature Kuunavang',
    'WTS Unded Rift Warden 25a' => 'Miniature Rift Warden',
    'WTS Ded Rift Warden 5e' => 'Miniature Rift Warden',
    'WTS unded mini dhuum 45a' => 'Miniature Dhuum',
];

foreach ($dedTests as $text => $expected) {
    $names = namesFor($matcher, $text);
    $ok = in_array($expected, $names, true);
    printf("%-34s => %-28s %s\n",
        $text,
        implode(', ', $names) ?: '(geen)',
        $ok ? 'OK' : 'FAIL'
    );
    if (!$ok) $fail++;
}

echo "\nAmbiguity checks\n";
$ambiguous = namesFor($matcher, 'WTS Dhuum 40a');
$ok = !in_array('Miniature Dhuum', $ambiguous, true);
printf("%-34s => %-28s %s\n",
    'WTS Dhuum 40a',
    implode(', ', $ambiguous) ?: '(geen)',
    $ok ? 'OK' : 'FAIL'
);
if (!$ok) $fail++;

if ($fail > 0) {
    fwrite(STDERR, "\nPhase 7E.2 FIX1 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.2 FIX1 smoke-test: OK\n";
echo "LET OP: deze test bewijst matcher-context. Na reparse controleren we dat ParserEngine\n";
echo "expliciet ded/unded ook werkelijk als dedication:* in market_key doorgeeft.\n";
