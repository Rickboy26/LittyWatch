<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
foreach(db()->query("SELECT lifecycle_status,quality_reason,COUNT(*) aantal FROM structured_offers GROUP BY lifecycle_status,quality_reason ORDER BY aantal DESC") as $r){
 printf("%-15s %-35s %d\n",$r['lifecycle_status'],$r['quality_reason']??'-',$r['aantal']);
}
