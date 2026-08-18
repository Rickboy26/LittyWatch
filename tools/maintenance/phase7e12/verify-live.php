<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

echo "=== Phase 7E.12 live verification ===\n";

foreach ([
    'catalog_first_unresolved',
    'low_confidence',
    'miniature_variant_unresolved',
    'insufficient_item_identity',
    'strict_catalog_generic',
    'service_or_noise'
] as $reason) {
    $st = db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");
    $st->execute([$reason]);
    printf("%-38s %d\n", $reason, (int)$st->fetchColumn());
}

$alcBad = (int)db()->query("
    SELECT COUNT(*)
    FROM structured_offers
    WHERE lower(COALESCE(raw_segment,'')) LIKE '%alc stack%'
      AND NOT (item_key='alcohol-point' AND quality_reason='catalog_match')
")->fetchColumn();

$unidBad = (int)db()->query("
    SELECT COUNT(*)
    FROM structured_offers
    WHERE lower(COALESCE(raw_segment,'')) LIKE '%unided gold%'
      AND NOT (item_key='unidentified-gold' AND quality_reason='catalog_match')
")->fetchColumn();

$dragonBad = (int)db()->query("
    SELECT COUNT(*)
    FROM structured_offers
    WHERE item_key='miniature-celestial-dragon'
      AND lower(COALESCE(raw_segment,'')) LIKE '%dragon staff%'
      AND quality_status <> 'rejected'
")->fetchColumn();

echo "\nAlcohol Point bad rows: {$alcBad}\n";
echo "Unidentified Gold bad rows: {$unidBad}\n";
echo "Dragon Staff false-mini doorgelaten: {$dragonBad}\n";

echo $alcBad===0 ? "OK: alc stacks canonical.\n" : "FAIL: alc stacks nog fout.\n";
echo $unidBad===0 ? "OK: unided gold canonical.\n" : "FAIL: unided gold nog fout.\n";
echo $dragonBad===0 ? "OK: Dragon Staff false-mini geblokkeerd.\n" : "FAIL: Dragon Staff false-mini lekt door.\n";

exit(($alcBad===0 && $unidBad===0 && $dragonBad===0) ? 0 : 1);
