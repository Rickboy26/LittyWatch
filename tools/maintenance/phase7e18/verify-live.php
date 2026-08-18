<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.18 live verification ===\n";

foreach([
    'catalog_first_unresolved',
    'miniature_variant_unresolved',
    'modifier_fragment_unresolved',
    'low_confidence',
    'service_or_noise',
    'collection_or_market_request'
] as $reason){
    $st=db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");
    $st->execute([$reason]);
    printf("%-38s %d\n",$reason,(int)$st->fetchColumn());
}

$tail=(int)db()->query("
    SELECT COUNT(*) FROM structured_offers
    WHERE (
        lower(COALESCE(raw_segment,'')) LIKE '%[x% left]%'
        OR lower(trim(COALESCE(raw_segment,'')))='stk'
    )
      AND quality_status<>'rejected'
")->fetchColumn();

$dragon=(int)db()->query("
    SELECT COUNT(*) FROM structured_offers
    WHERE item_key='miniature-celestial-dragon'
      AND lower(COALESCE(raw_segment,'')) LIKE '%dragon root%'
      AND quality_status<>'rejected'
")->fetchColumn();

$mallyx=(int)db()->query("
    SELECT COUNT(*) FROM structured_offers
    WHERE item_key IN ('mallyx','miniature-mallyx')
      AND lower(COALESCE(raw_segment,'')) LIKE 'mallyx''s %'
      AND quality_reason='miniature_variant_unresolved'
")->fetchColumn();

$claws=(int)db()->query("
    SELECT COUNT(*) FROM structured_offers
    WHERE lower(COALESCE(raw_segment,'')) LIKE '%claws of bro%'
      AND NOT (item_key='claws-of-the-broodmother' AND quality_reason='catalog_match')
")->fetchColumn();

echo "\nOrphan tail doorgelaten: {$tail}\n";
echo "Dragon Roots false miniature doorgelaten: {$dragon}\n";
echo "Mallyx named collision miniature rows: {$mallyx}\n";
echo "Claws of Bro bad rows: {$claws}\n";
