<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use LittyWatch\Market\VariantValidityGate;

$gate=new VariantValidityGate();
$sql="SELECT id,item,item_key,requirement,attribute_key,attribute_name,is_oldschool,is_inscribable,quality_status,lifecycle_status,raw_segment FROM structured_offers WHERE quality_status='accepted'";
$bad=[];
foreach(db()->query($sql) as $row){$check=$gate->inspect($row);if(!$check['allowed']){$row['reason']=$check['reason'];$bad[]=$row;}}
echo "=== IMPOSSIBLE MARKET VARIANTS ===\n";
echo "Aantal: ".count($bad)."\n\n";
foreach(array_slice($bad,0,100) as $r){printf("#%-7d %-28s q%-3s %-24s %-10s %s\n",$r['id'],$r['item'],$r['requirement']??'-',$r['attribute_name']?:($r['attribute_key']?:'-'),$r['lifecycle_status'],$r['reason']);echo "  ".$r['raw_segment']."\n";}
if(count($bad)>100)echo "... nog ".(count($bad)-100)." niet getoond\n";
