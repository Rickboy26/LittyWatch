<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ItemMatcher;

$catalog = new Catalog(db());
$matcher = new ItemMatcher($catalog);

$tests = [
    ['OBSI EDGE q11 8a', 'Obsidian Edge', true],
    ['Ghero 10a', 'Miniature Ghostly Hero', false],
    ['Ded Ghero 10a', 'Miniature Ghostly Hero', true],
    ["Miniature Shiro'ken Assassin unded 20a", "Miniature Shiro'ken Assassin", true],
    ['Outcast Dom 20a', 'Outcast Staff', true],
    ['Plag Illus 20a', 'Plagueborn Staff', true],
    ['Jade Sp 20a', 'Jade Staff', true],
    ['Tome Elite 1e', 'Elite Tome', false],
];

$failed = 0;
foreach ($tests as [$text,$expected,$shouldMatch]) {
    $matches = $matcher->matchAll($text);
    $names = array_map(static fn(array $m): string => (string)($m['item'] ?? ''), $matches);
    $matched = in_array($expected,$names,true);
    $ok = $shouldMatch ? $matched : !$matched;

    printf("%-42s expected=%-28s result=%-8s %s\n",
        $text,$expected,$matched?'MATCH':'NO_MATCH',$ok?'OK':'FAIL');

    if (!$ok) $failed++;
}

echo "\nActive learned aliases: ";
try {
    echo (int)db()->query("SELECT COUNT(*) FROM parser_learned_aliases WHERE active=1")->fetchColumn();
} catch (Throwable $e) {
    echo "table unavailable";
}
echo "\n";

if ($failed > 0) {
    fwrite(STDERR,"Smoke test FAILED: {$failed} test(s).\n");
    exit(1);
}
echo "Smoke test OK.\n";
