<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

echo "=== Phase 7E.8 live verification ===\n";

foreach ([
    'strict_catalog_generic',
    'miniature_variant_unresolved',
    'catalog_first_unresolved',
    'low_confidence',
    'impossible_bds_requirement',
] as $reason) {
    $st=db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");
    $st->execute([$reason]);
    printf("%-38s %d\n",$reason,(int)$st->fetchColumn());
}

echo "\nRecent BDS rejects:\n";
$st=db()->query("
SELECT so.id,so.item,so.requirement,so.attribute_key,so.quality_reason,m.message
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE lower(so.item)='bone dragon staff'
ORDER BY so.id DESC
LIMIT 20
");
foreach($st as $r){
    printf("#%d q=%s attr=%s %-30s | %s\n",
        $r['id'],
        $r['requirement']??'-',
        $r['attribute_key']??'-',
        $r['quality_reason']??'-',
        $r['message']
    );
}

echo "\nRecent miniature unresolved:\n";
$st=db()->query("
SELECT so.id,so.item,so.item_key,m.message
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE so.quality_reason='miniature_variant_unresolved'
ORDER BY so.id DESC
LIMIT 20
");
foreach($st as $r){
    printf("#%d %-32s %-32s | %s\n",
        $r['id'],$r['item']??'-',$r['item_key']??'-',$r['message']);
}
