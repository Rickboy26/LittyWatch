<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$pdo = db();

echo "=== Phase 7E.7 FIX3 Shiro'ken verification ===\n";

$sql = "
SELECT
    COALESCE(item, '-') AS item,
    COALESCE(item_key, '-') AS item_key,
    COALESCE(quality_reason, '-') AS quality_reason,
    COUNT(*) AS aantal
FROM structured_offers
WHERE lower(COALESCE(item,'')) LIKE '%shiro%ken%'
   OR lower(COALESCE(item_key,'')) LIKE '%shiro%ken%'
   OR lower(COALESCE(raw_segment,'')) LIKE '%shiro%ken%'
GROUP BY item,item_key,quality_reason
ORDER BY aantal DESC
";
foreach ($pdo->query($sql) as $r) {
    printf("%5d | %-36s | %-36s | %s\n",
        (int)$r['aantal'], (string)$r['item'], (string)$r['item_key'], (string)$r['quality_reason']);
}

$bad = (int)$pdo->query("SELECT COUNT(*) FROM structured_offers WHERE item_key='miniature_shiro_ken_assassin'")->fetchColumn();
$good = (int)$pdo->query("SELECT COUNT(*) FROM structured_offers WHERE item_key='miniature-shiro-ken-assassin'")->fetchColumn();
$unresolved = (int)$pdo->query("
SELECT COUNT(*) FROM structured_offers
WHERE quality_reason='catalog_first_unresolved'
AND (
 lower(COALESCE(item,'')) LIKE '%shiro%ken%'
 OR lower(COALESCE(item_key,'')) LIKE '%shiro%ken%'
 OR lower(COALESCE(raw_segment,'')) LIKE '%shiro%ken%'
)")->fetchColumn();

echo "\nLegacy underscore key: {$bad}\n";
echo "Canonical hyphen key: {$good}\n";
echo "catalog_first_unresolved Shiroken: {$unresolved}\n";

$fail=0;
if ($bad===0) echo "OK: geen legacy underscore Shiroken-key meer.\n"; else {$fail++; echo "FAIL: legacy underscore key bestaat nog.\n";}
if ($unresolved===0) echo "OK: Shiro'ken catalog_first_unresolved = 0.\n"; else {$fail++; echo "FAIL: unresolved blijft {$unresolved}.\n";}
if ($good>0) echo "OK: canonical Shiro'ken offers gevonden.\n"; else {$fail++; echo "FAIL: geen canonical Shiro'ken offers gevonden.\n";}
exit($fail ? 1 : 0);
