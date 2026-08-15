<?php
declare(strict_types=1);
$root=dirname(__DIR__,3); require $root.'/bootstrap.php';
$sql="SELECT raw_segment,item,COUNT(*) aantal FROM structured_offers WHERE quality_reason='catalog_first_unresolved' GROUP BY raw_segment,item ORDER BY aantal DESC LIMIT 50";
foreach(db()->query($sql) as $r)printf("%4d | %-32s | %s\n",$r['aantal'],$r['item']??'-',$r['raw_segment']??'-');
