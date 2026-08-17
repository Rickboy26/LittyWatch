<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$catalogFile = $root . '/app/Data/phase4f-items.json';
$canonicalKey = 'miniature-shiro-ken-assassin';
$legacyKey = 'miniature-shiroken-assassin';

$fail = 0;
function check7e7(bool $ok, string $label): void {
    global $fail;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) $fail++;
}

$data = json_decode((string)file_get_contents($catalogFile), true);
check7e7(is_array($data), 'phase4f-items.json geldige JSON');

$canonical = [];
$legacy = [];
foreach (($data ?: []) as $row) {
    if (($row['key'] ?? null) === $canonicalKey) $canonical[] = $row;
    if (($row['key'] ?? null) === $legacyKey) $legacy[] = $row;
}
check7e7(count($canonical) === 1, 'exact één canonical phase4f Shiroken-record');
check7e7(count($legacy) === 0, 'geen legacy phase4f Shiroken-key');
check7e7(($canonical[0]['name'] ?? '') === "Miniature Shiro'ken Assassin", 'canonical naam klopt');

$pdo = db();

$st = $pdo->prepare("SELECT COUNT(*) FROM kb_items WHERE key=?");
$st->execute([$legacyKey]);
check7e7((int)$st->fetchColumn() === 0, 'legacy kb_items-key verwijderd');

$st->execute([$canonicalKey]);
check7e7((int)$st->fetchColumn() <= 1, 'canonical kb_items-key niet gedupliceerd');

$cols = [];
foreach ($pdo->query("PRAGMA table_info(kb_aliases)") as $r) {
    $cols[(string)$r['name']] = true;
}
$refCol = isset($cols['item_key']) ? 'item_key' : (isset($cols['key']) ? 'key' : null);

if ($refCol !== null) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM kb_aliases WHERE {$refCol}=?");
    $st->execute([$legacyKey]);
    check7e7((int)$st->fetchColumn() === 0, 'geen kb_aliases meer op legacy key');
} else {
    check7e7(false, 'kb_aliases item-key kolom gevonden');
}

echo PHP_EOL;
if ($fail > 0) {
    echo "Phase 7E.7 smoke-test: {$fail} fout(en)." . PHP_EOL;
    exit(1);
}
echo "Phase 7E.7 smoke-test volledig OK." . PHP_EOL;
echo "Daarna pas: php tools/maintenance/reparse-all.php" . PHP_EOL;
