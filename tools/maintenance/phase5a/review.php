<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();
$limit=isset($argv[1])?max(1,(int)$argv[1]):50;

$labels=$db->query("SELECT key,label,description FROM parser_review_labels ORDER BY sort_order,key")->fetchAll(PDO::FETCH_ASSOC);
$st=$db->prepare("SELECT * FROM parser_residual_reviews WHERE decision IS NULL
ORDER BY CASE current_reason WHEN 'catalog_first_unresolved' THEN 0 WHEN 'miniature_variant_unresolved' THEN 1 WHEN 'miniature_context_conflict' THEN 2 ELSE 3 END,id
LIMIT ?");
$st->bindValue(1,$limit,PDO::PARAM_INT);$st->execute();
$rows=$st->fetchAll(PDO::FETCH_ASSOC);
if(!$rows){echo "Geen open review-items.\n";exit;}

$up=$db->prepare("UPDATE parser_residual_reviews SET decision=?,corrected_item=?,corrected_key=?,notes=?,reviewed_at=?,updated_at=? WHERE id=?");

foreach($rows as $row){
 echo "\n============================================================\n";
 echo "Review ID: {$row['id']} | Offer ID: {$row['structured_offer_id']}\n";
 echo "Reason:  {$row['current_reason']}\n";
 echo "Item:    {$row['item']}\n";
 echo "Segment: ".($row['raw_segment']?:'-')."\n";
 echo "Message: ".($row['raw_message']?:'-')."\n";
 $suggest=json_decode((string)$row['suggested_json'],true)?:[];
 if($suggest){
  echo "Suggestions:\n";
  foreach($suggest as $i=>$s)printf("  [S%d] %s [%s] %.2f %s\n",$i+1,$s['name'],$s['key'],$s['score'],$s['via']);
 }
 echo "Labels:\n";
 foreach($labels as $i=>$l)printf("  [%d] %-22s %s\n",$i+1,$l['key'],$l['label']);
 echo "  [s] skip\n  [q] quit\n";
 $choice=strtolower(trim((string)readline("Keuze: ")));
 if($choice==='q')break;
 if($choice===''||$choice==='s')continue;
 $idx=(int)$choice-1;
 if(!isset($labels[$idx])){echo "Ongeldig; overgeslagen.\n";continue;}
 $decision=$labels[$idx]['key'];$item=null;$key=null;
 if($decision==='correct_item'){
  if($suggest){
   $s=trim((string)readline("Suggestion nummer (1-".count($suggest)."), Enter=handmatig: "));
   if($s!=='' && isset($suggest[(int)$s-1])){
    $pick=$suggest[(int)$s-1];$item=$pick['name'];$key=$pick['key'];
   }
  }
  if($item===null){
   $item=trim((string)readline("Correct item name: "));
   $key=trim((string)readline("Correct item key (optioneel): "));
   if($key==='')$key=null;
  }
 }
 $notes=trim((string)readline("Notitie (optioneel): ")); if($notes==='')$notes=null;
 $now=gmdate('c');
 $up->execute([$decision,$item,$key,$notes,$now,$now,$row['id']]);
 echo "Opgeslagen: {$decision}\n";
}
