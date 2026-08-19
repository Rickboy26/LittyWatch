<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
echo "=== Phase 7E.19 live verification ===\n";
foreach(['catalog_first_unresolved','miniature_variant_unresolved','low_confidence','insufficient_item_identity','service_or_noise'] as $reason){
 $st=db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");
 $st->execute([$reason]);
 printf("%-38s %d\n",$reason,(int)$st->fetchColumn());
}
$salma=(int)db()->query("SELECT COUNT(*) FROM structured_offers so JOIN messages m ON m.id=so.message_id WHERE lower(m.message) LIKE '%el tonics%' AND so.item_key='miniature-princess-salma' AND so.quality_status<>'rejected'")->fetchColumn();
$kuuna=(int)db()->query("SELECT COUNT(*) FROM structured_offers so JOIN messages m ON m.id=so.message_id WHERE lower(m.message) LIKE '%el kuuna%' AND so.item_key IN ('miniature-kuunavang','kuuna') AND so.quality_status<>'rejected'")->fetchColumn();
$rock=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE lower(COALESCE(raw_segment,'')) LIKE '%rock stack%' AND NOT (item_key='market-rock-candy-stack' AND quality_reason='catalog_match')")->fetchColumn();
$wand=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE lower(trim(COALESCE(raw_segment,''))) IN ('wand wrappings:','wand wrappings') AND item_key='staff-wrapping-of-energy-storage' AND quality_status<>'rejected'")->fetchColumn();
echo "\nEL Salma false miniature: {$salma}\n";
echo "EL Kuuna false miniature: {$kuuna}\n";
echo "Rock Stack bad rows: {$rock}\n";
echo "Wand Wrappings false Staff Wrapping: {$wand}\n";
