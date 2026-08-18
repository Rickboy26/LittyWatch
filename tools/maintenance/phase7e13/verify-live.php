<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

echo "=== Phase 7E.13 live verification ===\n";
foreach (['catalog_first_unresolved','miniature_variant_unresolved','insufficient_item_identity','strict_catalog_generic','service_or_noise','modifier_fragment_unresolved'] as $reason) {
    $st = db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");
    $st->execute([$reason]);
    printf("%-38s %d\n", $reason, (int)$st->fetchColumn());
}

$ghostBad = (int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE item_key='miniature-ghostly-priest' AND (lower(COALESCE(raw_segment,'')) LIKE '% q9 %' OR lower(COALESCE(raw_segment,'')) LIKE 'q9 %' OR lower(COALESCE(raw_segment,'')) LIKE '% os %' OR lower(COALESCE(raw_segment,'')) LIKE '% hct %') AND quality_status <> 'rejected'")->fetchColumn();
$necroBad = (int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE lower(COALESCE(raw_segment,'')) LIKE '%of the necro%' AND lower(COALESCE(raw_segment,'')) LIKE '%scyt%' AND NOT (item_key='scythe-grip-of-the-necromancer' AND quality_reason='catalog_match')")->fetchColumn();
$icyBad = (int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE lower(COALESCE(raw_segment,'')) LIKE '%icedragon blade%' AND NOT (item_key='icy-dragon-sword' AND quality_reason='catalog_match')")->fetchColumn();

echo "\nGhostly weapon false-mini doorgelaten: {$ghostBad}\n";
echo "Necro scythe grip bad rows: {$necroBad}\n";
echo "Icy Dragon Sword bad rows: {$icyBad}\n";

exit(($ghostBad===0 && $necroBad===0 && $icyBad===0) ? 0 : 1);
