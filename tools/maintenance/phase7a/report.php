<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

echo "=== PHASE 7A LEARNED ALIASES ===\n";
try {
    foreach ($db->query("
        SELECT source,COUNT(*) aantal
        FROM parser_learned_aliases
        WHERE active=1
        GROUP BY source
        ORDER BY aantal DESC
    ") as $r) {
        printf("%-32s %d\n",$r['source'],$r['aantal']);
    }
} catch (Throwable $e) {
    echo "parser_learned_aliases niet beschikbaar.\n";
}

echo "\n=== CURRENT LIFECYCLE / QUALITY ===\n";
foreach ($db->query("
SELECT lifecycle_status,quality_reason,COUNT(*) aantal
FROM structured_offers
GROUP BY lifecycle_status,quality_reason
ORDER BY aantal DESC
") as $r) {
    printf("%-15s %-35s %d\n",$r['lifecycle_status'],$r['quality_reason']??'-',$r['aantal']);
}
