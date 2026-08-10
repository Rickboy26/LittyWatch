<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
$db=db();

echo "=== LIFECYCLE / QUALITY ===\n";
$sql="SELECT lifecycle_status, quality_reason, COUNT(*) AS aantal
      FROM structured_offers
      GROUP BY lifecycle_status, quality_reason
      ORDER BY aantal DESC";
foreach($db->query($sql) as $r){
    printf("%-15s %-35s %d\n",$r['lifecycle_status'],$r['quality_reason']??'-',$r['aantal']);
}

echo "\n=== ECHTE CATALOG BACKLOG ===\n";
$sql="SELECT item, COUNT(*) AS aantal
      FROM structured_offers
      WHERE lifecycle_status='rejected'
        AND quality_reason='catalog_first_unresolved'
      GROUP BY item
      ORDER BY aantal DESC
      LIMIT 80";
foreach($db->query($sql) as $r){
    printf("%-50s %d\n",$r['item'],$r['aantal']);
}

foreach(['collection_or_market_request','service_or_noise','modifier_fragment_unresolved'] as $reason){
    echo "\n=== {$reason} ===\n";
    $stmt=$db->prepare("SELECT item, COUNT(*) AS aantal
                        FROM structured_offers
                        WHERE lifecycle_status='rejected'
                          AND quality_reason=?
                        GROUP BY item
                        ORDER BY aantal DESC
                        LIMIT 30");
    $stmt->execute([$reason]);
    foreach($stmt as $r){
        printf("%-50s %d\n",$r['item'],$r['aantal']);
    }
}
