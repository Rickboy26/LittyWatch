<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Margo rows ===\n";
$sql="
SELECT
 m.message,
 so.item,
 so.quality_status,
 so.quality_reason,
 so.lifecycle_status,
 COUNT(*) OVER () total
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE LOWER(so.raw_segment) LIKE '%margo%'
ORDER BY so.id DESC
LIMIT 100
";

foreach(db()->query($sql) as $r){
    echo "MSG: ".$r['message'].PHP_EOL;
    echo " -> ".$r['item']
       ." | ".$r['quality_status']
       ." | ".$r['quality_reason']
       ." | ".$r['lifecycle_status']
       .PHP_EOL.PHP_EOL;
}
