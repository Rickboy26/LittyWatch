<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
echo "=== Phase 7E.11 live verification ===\n";
foreach(['catalog_first_unresolved','low_confidence','miniature_variant_unresolved'] as $reason){
 $st=db()->prepare("SELECT COUNT(*) FROM structured_offers WHERE quality_reason=?");
 $st->execute([$reason]);
 printf("%-38s %d\n",$reason,(int)$st->fetchColumn());
}
foreach([
 ["Kazhad's Fortune",'kazhad-s-fortune'],
 ['Superior Rune of Holding','superior-rune-of-holding'],
 ['Rune of Belt Holding','rune-of-belt-holding']
] as [$name,$key]){
 $st=db()->prepare("SELECT SUM(quality_reason='catalog_match') good,SUM(quality_reason='catalog_first_unresolved') bad FROM structured_offers WHERE item_key=? OR lower(item)=lower(?)");
 $st->execute([$key,$name]);
 $r=$st->fetch(PDO::FETCH_ASSOC);
 printf("%-30s match=%d unresolved=%d\n",$name,(int)($r['good']??0),(int)($r['bad']??0));
}
