<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
echo "=== Phase 7E.20 live verification ===\n";
foreach(['catalog_first_unresolved','miniature_variant_unresolved','low_confidence','insufficient_item_identity'] as $reason){
 $st=db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");
 $st->execute([$reason]);
 printf("%-38s %d\n",$reason,(int)$st->fetchColumn());
}
$blueBad=(int)db()->query("
SELECT COUNT(*) FROM structured_offers
WHERE (
 lower(COALESCE(raw_segment,'')) LIKE '%blue dye%'
 OR lower(COALESCE(raw_segment,'')) LIKE '%bleu dye%'
)
AND NOT (item_key='blue-dye' AND quality_reason='catalog_match')
")->fetchColumn();
$ancientBad=(int)db()->query("
SELECT COUNT(*) FROM structured_offers
WHERE lower(COALESCE(raw_segment,'')) LIKE '%ancient armor%'
AND NOT (item_key='ancient-armor-remnant' AND quality_reason='catalog_match')
")->fetchColumn();
$gladBad=(int)db()->query("
SELECT COUNT(*) FROM structured_offers
WHERE lower(COALESCE(raw_segment,'')) LIKE '%glad box%'
AND NOT (item_key='gladiator-s-zaishen-strongbox' AND quality_reason='catalog_match')
")->fetchColumn();
echo "\nBlue Dye bad rows: {$blueBad}\n";
echo "Ancient Armor bad rows: {$ancientBad}\n";
echo "Glad Boxes bad rows: {$gladBad}\n";
