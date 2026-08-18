<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
echo "=== Phase 7E.14 live verification ===\n";
foreach(['catalog_first_unresolved','insufficient_item_identity','low_confidence','service_or_noise','strict_catalog_generic'] as $reason){
 $st=db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");$st->execute([$reason]);
 printf("%-38s %d\n",$reason,(int)$st->fetchColumn());
}
$ab=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE lower(COALESCE(raw_segment,'')) LIKE '%abnormal seed%' AND NOT (item_key='unnatural-seed' AND quality_reason='catalog_match')")->fetchColumn();
$bo=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE lower(COALESCE(raw_segment,'')) LIKE '%bords eye%' AND NOT (item_key='birdseye' AND quality_reason='catalog_match')")->fetchColumn();
$bl=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE item_key='blessing-of-war' AND lower(COALESCE(raw_segment,'')) IN ('bow','axe','hammer','sword','dagger') AND quality_status<>'rejected'")->fetchColumn();
echo "\nAbnormal Seed bad rows: {$ab}\nBords Eyes bad rows: {$bo}\nFalse Blessing weapon rows doorgelaten: {$bl}\n";
exit(($ab===0&&$bo===0&&$bl===0)?0:1);
