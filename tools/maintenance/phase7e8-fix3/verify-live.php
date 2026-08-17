<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.8 FIX3 live verification ===\n";

foreach ([
    'miniature_variant_unresolved',
    'strict_catalog_generic',
    'catalog_first_unresolved',
    'impossible_bds_requirement'
] as $reason) {
    $st=db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");
    $st->execute([$reason]);
    printf("%-38s %d\n",$reason,(int)$st->fetchColumn());
}

$falseFortune=(int)db()->query("
SELECT COUNT(*)
FROM structured_offers
WHERE lower(COALESCE(raw_segment,'')) LIKE '%fortune%'
  AND (
      lower(COALESCE(item,'')) LIKE 'miniature %'
      OR lower(replace(COALESCE(item_key,''),'_','-')) LIKE 'miniature-%'
  )
")->fetchColumn();

$genericMini=(int)db()->query("
SELECT COUNT(*)
FROM structured_offers
WHERE (
    lower(trim(COALESCE(item,''))) IN ('miniature','mini')
    OR lower(trim(replace(COALESCE(item_key,''),'_','-'))) IN ('miniature','mini')
)
AND quality_reason <> 'strict_catalog_generic'
")->fetchColumn();

echo "\nFalse Fortune -> miniature rows: {$falseFortune}\n";
echo "Generic Miniature rows not strict_catalog_generic: {$genericMini}\n";

echo $falseFortune===0
    ? "OK: geen Fortune false-miniatures.\n"
    : "FAIL: Fortune false-miniatures bestaan nog.\n";

echo $genericMini===0
    ? "OK: generieke Miniature rows worden correct rejected.\n"
    : "FAIL: generieke Miniature rows lekken nog door.\n";

exit(($falseFortune===0 && $genericMini===0) ? 0 : 1);
