<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

use LittyWatch\Market\VariantNormalizer;

$fail = 0;
function chk(bool $ok, string $label, mixed $actual=null): void {
    global $fail;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label;
    if (!$ok && $actual !== null) echo ' [actual=' . (string)$actual . ']';
    echo PHP_EOL;
    if (!$ok) $fail++;
}

$n = new VariantNormalizer();

$r = $n->normalize('miniature-shiro-ken-assassin', null, null, null, false, false, [], []);
chk(($r['item_key'] ?? '') === 'miniature-shiro-ken-assassin',
    "Shiro'ken canonical item_key blijft hyphenated", $r['item_key'] ?? '');

$r2 = $n->normalize('miniature_shiro_ken_assassin', null, null, null, false, false, [], []);
chk(($r2['item_key'] ?? '') === 'miniature-shiro-ken-assassin',
    'legacy underscore item_key canonicaliseert naar hyphens', $r2['item_key'] ?? '');

$r3 = $n->normalize('bone-dragon-staff', 9, 'domination_magic', 'Domination Magic', true, false, [], []);
chk(($r3['item_key'] ?? '') === 'bone-dragon-staff',
    'ander catalog item behoudt hyphen key', $r3['item_key'] ?? '');

$mk = (string)($r3['market_key'] ?? '');
chk(str_contains($mk, 'bone-dragon-staff'), 'market_key bevat canonical base item');
chk(str_contains($mk, 'q:9'), 'requirement blijft in market_key');
chk(str_contains($mk, 'attribute:domination_magic'), 'attribute underscore-token blijft ongewijzigd');
chk(str_contains($mk, 'oldschool:1'), 'oldschool variant blijft ongewijzigd');

echo PHP_EOL;
if ($fail) {
    echo "Phase 7E.7 FIX3 smoke-test: {$fail} fout(en).\n";
    exit(1);
}
echo "Phase 7E.7 FIX3 smoke-test volledig OK.\n";
echo "Daarna: php tools/maintenance/reparse-all.php\n";
echo "En daarna: php tools/maintenance/phase7e7/verify-after-reparse.php\n";
