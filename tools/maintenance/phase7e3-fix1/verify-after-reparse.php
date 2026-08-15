<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Gerichte miniature residuals ===\n";
$sql="
SELECT so.raw_segment,so.item,so.quality_reason,COUNT(*) aantal
FROM structured_offers so
WHERE
       LOWER(so.raw_segment) LIKE '%ghost of althea%'
    OR LOWER(so.raw_segment) LIKE '%dagnar%'
    OR LOWER(so.raw_segment) LIKE '%black beast%'
    OR LOWER(so.raw_segment) LIKE '%candysmith%'
    OR LOWER(so.raw_segment) LIKE '%prince rurik%'
    OR LOWER(so.raw_segment) IN ('unded','ded','undedicated','dedicated')
GROUP BY so.raw_segment,so.item,so.quality_reason
ORDER BY aantal DESC
LIMIT 100
";
foreach(db()->query($sql) as $r){
 printf("%4d | %-34s | %-30s | %s\n",
  $r['aantal'],$r['item']??'-',$r['quality_reason']??'-',$r['raw_segment']??'-');
}

echo "\n=== catalog_first_unresolved top ===\n";
$sql="
SELECT raw_segment,item,COUNT(*) aantal
FROM structured_offers
WHERE quality_reason='catalog_first_unresolved'
GROUP BY raw_segment,item
ORDER BY aantal DESC
LIMIT 50
";
foreach(db()->query($sql) as $r){
 printf("%4d | %-32s | %s\n",$r['aantal'],$r['item']??'-',$r['raw_segment']??'-');
}
