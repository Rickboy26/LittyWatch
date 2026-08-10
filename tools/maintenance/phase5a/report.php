<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

echo "=== PHASE 5A REVIEW STATUS ===\n";
foreach($db->query("SELECT COALESCE(decision,'OPEN') decision,COUNT(*) aantal FROM parser_residual_reviews GROUP BY COALESCE(decision,'OPEN') ORDER BY aantal DESC") as $r){
 printf("%-28s %d\n",$r['decision'],$r['aantal']);
}
echo "\n=== OPEN PER REASON ===\n";
foreach($db->query("SELECT current_reason,COUNT(*) aantal FROM parser_residual_reviews WHERE decision IS NULL GROUP BY current_reason ORDER BY aantal DESC") as $r){
 printf("%-35s %d\n",$r['current_reason'],$r['aantal']);
}
echo "\n=== TOP OPEN CATALOG BACKLOG ===\n";
foreach($db->query("SELECT item,COUNT(*) aantal FROM parser_residual_reviews WHERE decision IS NULL AND current_reason='catalog_first_unresolved' GROUP BY item ORDER BY aantal DESC LIMIT 60") as $r){
 printf("%-50s %d\n",$r['item'],$r['aantal']);
}
