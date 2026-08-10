<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();
$out=dirname(__DIR__,3).'/data/exports';
if(!is_dir($out))mkdir($out,0775,true);
$stamp=date('Ymd-His');

function dump5a(PDO $db,string $where,string $path):int{
 $fh=fopen($path,'wb');$count=0;
 $sql="SELECT id,structured_offer_id,message_id,item,raw_segment,raw_message,current_reason,suggested_json,decision,corrected_item,corrected_key,notes,reviewed_at FROM parser_residual_reviews WHERE {$where} ORDER BY id";
 foreach($db->query($sql) as $r){
  $r['suggestions']=json_decode((string)$r['suggested_json'],true)?:[];
  unset($r['suggested_json']);
  fwrite($fh,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");$count++;
 }
 fclose($fh);return $count;
}
$rPath="$out/littywatch-phase5a-reviewed-$stamp.ndjson";
$pPath="$out/littywatch-phase5a-pending-$stamp.ndjson";
$r=dump5a($db,"decision IS NOT NULL",$rPath);
$p=dump5a($db,"decision IS NULL",$pPath);
echo "Reviewed: {$r} -> {$rPath}\n";
echo "Pending:  {$p} -> {$pPath}\n";
