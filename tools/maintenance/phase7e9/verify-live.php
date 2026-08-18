<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.9 live verification ===\n";

foreach([
    'catalog_first_unresolved',
    'low_confidence',
    'miniature_variant_unresolved',
    'strict_catalog_generic',
    'collection_or_market_request'
] as $reason){
    $st=db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");
    $st->execute([$reason]);
    printf("%-38s %d\n",$reason,(int)$st->fetchColumn());
}

$elMini=(int)db()->query("
SELECT COUNT(*) FROM structured_offers
WHERE lower(COALESCE(raw_segment,'')) LIKE '%ghostly priest%'
  AND lower(COALESCE(item,'')) LIKE 'miniature ghostly priest%'
")->fetchColumn();

$badKuuna=(int)db()->query("
SELECT COUNT(*) FROM structured_offers
WHERE lower(COALESCE(item,''))='miniature kuunavang'
  AND replace(lower(COALESCE(item_key,'')),'_','-')='kuuna'
")->fetchColumn();

echo "\nEL Ghostly Priest false-miniature rows: {$elMini}\n";
echo "Kuunavang rows still using key=kuuna: {$badKuuna}\n";

echo $elMini===0 ? "OK: EL Ghostly Priest mini false-positive = 0.\n" : "FAIL: EL Ghostly Priest false miniature bestaat nog.\n";
echo $badKuuna===0 ? "OK: Kuunavang review identity canonical.\n" : "FAIL: Kuunavang key=kuuna bestaat nog.\n";

exit(($elMini===0 && $badKuuna===0)?0:1);
