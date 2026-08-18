<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);require $root.'/bootstrap.php';
echo "=== Phase 7E.15 live verification ===\n";
foreach(['catalog_first_unresolved','low_confidence','miniature_variant_unresolved','service_or_noise'] as $reason){$st=db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");$st->execute([$reason]);printf("%-38s %d\n",$reason,(int)$st->fetchColumn());}
$golds=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE lower(COALESCE(raw_segment,'')) LIKE '%golds%' AND NOT (item_key='market-inscribable-golds' AND quality_reason='catalog_match')")->fetchColumn();
$egg=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE lower(trim(COALESCE(raw_segment,''))) LIKE 'egg %' AND NOT (item_key='golden-egg' AND quality_reason='catalog_match')")->fetchColumn();
$cake=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE lower(COALESCE(raw_segment,'')) LIKE 'd-cake%' AND NOT (item_key='delicious-cake' AND quality_reason='catalog_match')")->fetchColumn();
echo "\nInscribable Golds bad rows: {$golds}\nEgg bad rows: {$egg}\nD-Cakes bad rows: {$cake}\n";exit(($golds===0&&$egg===0&&$cake===0)?0:1);
