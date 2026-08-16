<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.5 FIX7 post-reparse ===\n";

$bad=db()->query("
SELECT COUNT(*)
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE (
       LOWER(m.message) LIKE '%ghostly hero''s strongbox%'
    OR LOWER(m.message) LIKE '%ghostly heros strongbox%'
    OR LOWER(m.message) LIKE '%ghostly hero strongbox%'
)
AND (
       LOWER(TRIM(so.item))='miniature ghostly hero'
    OR LOWER(TRIM(so.item_key)) IN (
        'ghostly_hero','ghostly-hero',
        'miniature_ghostly_hero','miniature-ghostly-hero'
    )
)
")->fetchColumn();

echo "Ghostly Hero Strongbox miniature collisions: ".(int)$bad.PHP_EOL;

echo "\n=== catalog_first_unresolved top ===\n";
foreach(db()->query("
SELECT item,raw_segment,COUNT(*) n
FROM structured_offers
WHERE quality_reason='catalog_first_unresolved'
GROUP BY item,raw_segment
ORDER BY n DESC
LIMIT 50
") as $r){
    printf("%4d | %-32s | %s\n",$r['n'],$r['item'],$r['raw_segment']);
}
