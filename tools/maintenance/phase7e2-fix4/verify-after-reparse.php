<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Accepted concrete miniatures zonder dedication ===\n";
$sql="SELECT COUNT(*) FROM structured_offers
WHERE lower(item) LIKE 'miniature %'
AND quality_status='accepted'
AND normalized_market_key NOT LIKE '%|dedication:dedicated%'
AND normalized_market_key NOT LIKE '%|dedication:undedicated%'";
echo "Aantal: ".(int)db()->query($sql)->fetchColumn().PHP_EOL.PHP_EOL;

echo "=== Dedicated miniatures maar nog variant_unresolved ===\n";
$sql="SELECT COUNT(*) FROM structured_offers
WHERE lower(item) LIKE 'miniature %'
AND quality_reason='miniature_variant_unresolved'
AND (normalized_market_key LIKE '%|dedication:dedicated%' OR normalized_market_key LIKE '%|dedication:undedicated%')";
echo "Aantal: ".(int)db()->query($sql)->fetchColumn().PHP_EOL.PHP_EOL;

echo "=== Quality breakdown ===\n";
$sql="SELECT lifecycle_status,quality_reason,COUNT(*) aantal
FROM structured_offers
GROUP BY lifecycle_status,quality_reason
ORDER BY aantal DESC";
foreach(db()->query($sql) as $r){
 printf("%-15s %-35s %d\n",$r['lifecycle_status'],$r['quality_reason']??'-',$r['aantal']);
}
