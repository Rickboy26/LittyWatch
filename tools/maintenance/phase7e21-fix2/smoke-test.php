<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$fail=0;
function c(bool $ok,string $label,mixed $actual=null):void{
 global $fail;
 echo ($ok?'OK   ':'FAIL ').$label;
 if(!$ok&&$actual!==null) echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
 echo PHP_EOL;
 if(!$ok)$fail++;
}
$file=$root.'/app/Market/Phase7E21AcceptedSafetyGuard.php';
c(is_file($file),'guard file bestaat');
if(is_file($file)) require_once $file;
$loaded=class_exists(\LittyWatch\Market\Phase7E21AcceptedSafetyGuard::class);
c($loaded,'guard class laadbaar');

$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
c(str_contains((string)$writer,'LITTYWATCH_PHASE7E21_ACCEPTED_SAFETY'),'final persistence marker aanwezig');

if($loaded){
 $g=new \LittyWatch\Market\Phase7E21AcceptedSafetyGuard();

 $frog=$g->repair([
  'item'=>'Spawning Staff','item_key'=>'spawning-staff','requirement'=>9,'attribute_key'=>'spawning_power',
  'raw_segment'=>'Scepter Q9 spawn 30A',
  '_message'=>'WTS BDS Q9 illu 30A --- Froggy Q9 spawn 30A --- CC q9 Dom 40A',
  'quality_status'=>'accepted','quality_reason'=>'catalog_match','confidence'=>0.92
 ]);
 c(($frog['item_key']??'')==='frog-scepter','Froggy Scepter => Frog Scepter',$frog['item_key']??null);

 $bow=$g->repair([
  'item'=>'Blessing of War','item_key'=>'blessing-of-war','raw_segment'=>'Bow 1a/ea',
  '_message'=>'WTS Azure Recurve/Short Bow 1a/ea','quality_status'=>'accepted','quality_reason'=>'catalog_match'
 ]);
 c(($bow['quality_reason']??'')==='accepted_named_item_collision','Bow false Blessing geblokkeerd',$bow['quality_reason']??null);

 $rune=$g->repair([
  'item'=>'Paragon Rune of Superior Command','item_key'=>'paragon-rune-of-superior-command',
  'raw_segment'=>'Shield Command 1A','_message'=>'WTB Opressor Shield Command 1A',
  'quality_status'=>'accepted','quality_reason'=>'catalog_match'
 ]);
 c(($rune['quality_reason']??'')==='accepted_item_family_collision','Command Shield mag geen Rune worden',$rune['quality_reason']??null);

 $cel=$g->repair([
  'item'=>'Celestial Shield','item_key'=>'celestial-shield','requirement'=>8,
  'attribute_key'=>'fire_magic','attribute_name'=>'Fire Magic',
  'raw_segment'=>'Celestial Shield r8 Tact +10 Fire/-2we',
  'quality_status'=>'accepted','quality_reason'=>'catalog_match'
 ]);
 c(($cel['quality_status']??'')==='accepted','+10 Fire shield blijft accepted',$cel['quality_status']??null);
 c(($cel['attribute_key']??'')==='tactics','shield requirement hersteld naar Tactics',$cel['attribute_key']??null);

 $shadow=$g->repair([
  'item'=>'Shadow Shield','item_key'=>'shadow-shield','requirement'=>9,
  'attribute_key'=>'fire_magic','attribute_name'=>'Fire Magic',
  'raw_segment'=>'q9 str Shadow Shield +1 Fire Magic 20%',
  'quality_status'=>'accepted','quality_reason'=>'catalog_match'
 ]);
 c(($shadow['attribute_key']??'')==='strength','Shadow Shield requirement hersteld naar Strength',$shadow['attribute_key']??null);
}
echo PHP_EOL;
if($fail){echo "Phase 7E.21 FIX2 smoke-test: {$fail} fout(en).".PHP_EOL;exit(1);}
echo "Phase 7E.21 FIX2 smoke-test volledig OK.".PHP_EOL;
echo "Daarna opnieuw live-market reset; geen reparse-all.".PHP_EOL;
