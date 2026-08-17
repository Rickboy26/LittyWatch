<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

echo "=== Phase 7E.8 FIX4 live verification ===\n";

$double = (int)db()->query("
    SELECT COUNT(*)
    FROM structured_offers
    WHERE lower(COALESCE(item,'')) LIKE '%''s''s fortune%'
       OR lower(COALESCE(item_key,'')) LIKE '%-s-s-fortune%'
")->fetchColumn();

$kazhadMini = (int)db()->query("
    SELECT COUNT(*)
    FROM structured_offers
    WHERE lower(COALESCE(raw_segment,'')) LIKE '%kazhad%fortune%'
      AND (
        lower(COALESCE(item,'')) LIKE 'miniature %'
        OR lower(replace(COALESCE(item_key,''),'_','-')) LIKE 'miniature-%'
      )
")->fetchColumn();

echo "Dubbele possessive Fortune rows: {$double}\n";
echo "Kazhad Fortune false-miniature rows: {$kazhadMini}\n";

echo $double===0 ? "OK: geen 's's Fortune meer.\n" : "FAIL: dubbele possessive bestaat nog.\n";
echo $kazhadMini===0 ? "OK: Kazhad Fortune is geen miniature.\n" : "FAIL: Kazhad Fortune false miniature bestaat nog.\n";

exit(($double===0 && $kazhadMini===0) ? 0 : 1);
