<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$pdo = db();

echo "=== Phase 7E.7 Shiro'ken verification ===\n";

$offerCols = [];
foreach ($pdo->query("PRAGMA table_info(structured_offers)") as $r) {
    $offerCols[(string)$r['name']] = true;
}

$parts = [];
if (isset($offerCols['item'])) $parts[] = "lower(COALESCE(item,'')) LIKE '%shiro%ken%'";
if (isset($offerCols['item_key'])) $parts[] = "lower(COALESCE(item_key,'')) LIKE '%shiro%ken%'";
if (isset($offerCols['raw_segment'])) $parts[] = "lower(COALESCE(raw_segment,'')) LIKE '%shiro%ken%'";
if (isset($offerCols['raw_item'])) $parts[] = "lower(COALESCE(raw_item,'')) LIKE '%shiro%ken%'";

if (!$parts) {
    echo "ERROR: geen bruikbare Shiroken zoekkolommen in structured_offers.\n";
    exit(1);
}
$whereShiro = '(' . implode(' OR ', $parts) . ')';

$itemExpr = isset($offerCols['item']) ? "COALESCE(item,'-')" : "'-'";
$keyExpr = isset($offerCols['item_key']) ? "COALESCE(item_key,'-')" : "'-'";
$reasonExpr = isset($offerCols['quality_reason']) ? "COALESCE(quality_reason,'-')" : "'-'";

$sql = "
SELECT
    {$itemExpr} AS item,
    {$keyExpr} AS item_key,
    {$reasonExpr} AS quality_reason,
    COUNT(*) AS aantal
FROM structured_offers
WHERE {$whereShiro}
GROUP BY item, item_key, quality_reason
ORDER BY aantal DESC
";

foreach ($pdo->query($sql) as $r) {
    printf(
        "%5d | %-36s | %-34s | %s\n",
        (int)$r['aantal'],
        (string)$r['item'],
        (string)$r['item_key'],
        (string)$r['quality_reason']
    );
}

echo "\n=== catalog_first_unresolved Shiroken ===\n";
if (!isset($offerCols['quality_reason'])) {
    echo "SKIP: quality_reason ontbreekt.\n";
    exit(0);
}

$sql = "
SELECT COUNT(*)
FROM structured_offers
WHERE quality_reason='catalog_first_unresolved'
  AND {$whereShiro}
";
$count = (int)$pdo->query($sql)->fetchColumn();
echo "Resterend: {$count}\n";

if ($count === 0) {
    echo "OK: Shiro'ken Assassin catalog_first_unresolved groep is verdwenen.\n";
    exit(0);
}
echo "LET OP: er zijn nog {$count} Shiro'ken catalog_first_unresolved records.\n";
exit(1);
