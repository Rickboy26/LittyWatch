<?php
declare(strict_types=1);
$root=dirname(__DIR__,3); require $root.'/bootstrap.php';

echo "=== Orphan Miniature state rows ===\n";
$sql="SELECT raw_segment,item,quality_reason,COUNT(*) aantal
FROM structured_offers
WHERE LOWER(item)='miniature'
AND (
 LOWER(TRIM(raw_segment)) IN ('unded','ded','undedicated','dedicated','unded/','ded/')
 OR LOWER(TRIM(raw_segment)) GLOB 'unded [0-9]*'
 OR LOWER(TRIM(raw_segment)) GLOB 'ded [0-9]*'
)
GROUP BY raw_segment,item,quality_reason
ORDER BY aantal DESC";
$rows=db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$total=0;
foreach($rows as $r){$total+=(int)$r['aantal'];printf("%4d | %-28s | %s\n",$r['aantal'],$r['quality_reason'],$r['raw_segment']);}
echo "Totaal: $total\n\n";

echo "=== Dedicated miniatures nog variant_unresolved ===\n";
$sql="SELECT COUNT(*) FROM structured_offers
WHERE quality_reason='miniature_variant_unresolved'
AND (normalized_market_key LIKE '%|dedication:dedicated%' OR normalized_market_key LIKE '%|dedication:undedicated%')";
echo "Aantal: ".(int)db()->query($sql)->fetchColumn().PHP_EOL.PHP_EOL;

echo "=== catalog_first_unresolved top ===\n";
$sql="SELECT raw_segment,item,COUNT(*) aantal FROM structured_offers
WHERE quality_reason='catalog_first_unresolved'
GROUP BY raw_segment,item ORDER BY aantal DESC LIMIT 50";
foreach(db()->query($sql) as $r)printf("%4d | %-32s | %s\n",$r['aantal'],$r['item']??'-',$r['raw_segment']??'-');
