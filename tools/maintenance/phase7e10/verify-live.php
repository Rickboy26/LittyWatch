<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.10 live verification ===\n";

foreach([
    'catalog_first_unresolved',
    'low_confidence',
    'miniature_variant_unresolved',
    'strict_catalog_generic',
    'collection_or_market_request',
    'service_or_noise'
] as $reason){
    $st=db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");
    $st->execute([$reason]);
    printf("%-38s %d\n",$reason,(int)$st->fetchColumn());
}

$falseDragon=(int)db()->query("
SELECT COUNT(*) FROM structured_offers
WHERE lower(COALESCE(raw_segment,'')) LIKE '%staves%'
  AND lower(COALESCE(raw_segment,'')) LIKE '%dragon%'
  AND item_key='miniature-celestial-dragon'
")->fetchColumn();

$boneLow=(int)db()->query("
SELECT COUNT(*) FROM structured_offers
WHERE item_key='bone' AND quality_reason='low_confidence'
")->fetchColumn();

echo "\nStaff-context false Celestial Dragon: {$falseDragon}\n";
echo "Bone low_confidence rows: {$boneLow}\n";

echo $falseDragon===0 ? "OK: staff Dragon false-mini = 0.\n" : "FAIL: false Celestial Dragon bestaat nog.\n";
echo $boneLow===0 ? "OK: Bone stack canonical.\n" : "FAIL: Bone blijft low-confidence.\n";

exit(($falseDragon===0 && $boneLow===0)?0:1);
