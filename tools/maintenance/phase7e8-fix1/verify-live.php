<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

echo "=== Phase 7E.8 FIX1 live verification ===\n";

foreach([
    'miniature_variant_unresolved',
    'strict_catalog_generic',
    'catalog_first_unresolved',
    'impossible_bds_requirement'
] as $reason){
    $st=db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");
    $st->execute([$reason]);
    printf("%-38s %d\n",$reason,(int)$st->fetchColumn());
}

echo "\nFortune / Prophecy rows:\n";
foreach(db()->query("
SELECT so.id,so.item,so.item_key,so.quality_status,so.quality_reason,so.raw_segment
FROM structured_offers so
WHERE lower(COALESCE(so.raw_segment,'')) LIKE '%fortune%'
   OR lower(COALESCE(so.raw_segment,'')) LIKE '%prophecy%'
ORDER BY so.id DESC LIMIT 30
") as $r){
    printf("#%d | %-30s | %-30s | %-8s | %-28s | %s\n",
        $r['id'],$r['item']??'-',$r['item_key']??'-',$r['quality_status']??'-',$r['quality_reason']??'-',$r['raw_segment']??'-');
}

echo "\nGeneric Miniature rows:\n";
foreach(db()->query("
SELECT id,item,item_key,quality_status,quality_reason,raw_segment
FROM structured_offers
WHERE lower(trim(COALESCE(item,''))) IN ('miniature','mini')
   OR lower(trim(replace(COALESCE(item_key,''),'_','-'))) IN ('miniature','mini')
ORDER BY id DESC LIMIT 30
") as $r){
    printf("#%d | %-12s | %-12s | %-8s | %-28s | %s\n",
        $r['id'],$r['item']??'-',$r['item_key']??'-',$r['quality_status']??'-',$r['quality_reason']??'-',$r['raw_segment']??'-');
}
