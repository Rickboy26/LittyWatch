<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$pdo = db();

echo "=== Phase 7E.7 Shiro'ken verification ===\n";

$sql = "
SELECT
    COALESCE(so.item, '-') AS item,
    COALESCE(so.item_key, '-') AS item_key,
    COALESCE(so.quality_reason, '-') AS quality_reason,
    COUNT(*) AS aantal
FROM structured_offers so
WHERE lower(COALESCE(so.item,'')) LIKE '%shiro%ken%'
   OR lower(COALESCE(so.item_key,'')) LIKE '%shiro%ken%'
   OR lower(COALESCE(so.raw_segment,'')) LIKE '%shiro%ken%'
GROUP BY so.item, so.item_key, so.quality_reason
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
$sql = "
SELECT COUNT(*)
FROM structured_offers
WHERE quality_reason='catalog_first_unresolved'
  AND (
      lower(COALESCE(item,'')) LIKE '%shiro%ken%'
      OR lower(COALESCE(item_key,'')) LIKE '%shiro%ken%'
      OR lower(COALESCE(raw_segment,'')) LIKE '%shiro%ken%'
  )
";
$count = (int)$pdo->query($sql)->fetchColumn();
echo "Resterend: {$count}\n";

if ($count === 0) {
    echo "OK: Shiro'ken Assassin catalog_first_unresolved groep is verdwenen.\n";
    exit(0);
}
echo "LET OP: er zijn nog {$count} Shiro'ken catalog_first_unresolved records.\n";
exit(1);
