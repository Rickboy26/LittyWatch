<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
echo "=== Phase 7E.21 FIX2 Accepted Safety verification ===".PHP_EOL;

$checks=[
 'Scepter -> Staff false accepted'=>"
   lower(COALESCE(raw_segment,'')) LIKE '%scepter%'
   AND lower(COALESCE(item,'')) LIKE '%staff%'
   AND quality_status='accepted'
 ",
 'Bow -> Blessing of War false accepted'=>"
   item_key='blessing-of-war'
   AND lower(COALESCE(raw_segment,'')) LIKE '%bow%'
   AND lower(COALESCE(raw_segment,'')) NOT LIKE '%blessing of war%'
   AND quality_status='accepted'
 ",
 'Rune <- Shield false accepted'=>"
   lower(COALESCE(item_key,'')) LIKE '%rune%'
   AND lower(COALESCE(raw_segment,'')) LIKE '%shie%'
   AND quality_status='accepted'
 "
];

foreach($checks as $label=>$where){
 $n=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE {$where}")->fetchColumn();
 printf("%-45s %d".PHP_EOL,$label,$n);
}

$rows=db()->query("
 SELECT item_key,attribute_key,raw_segment
 FROM structured_offers
 WHERE quality_status='accepted'
   AND (lower(COALESCE(item_key,'')) LIKE '%shield%' OR lower(COALESCE(item,'')) LIKE '%shield%')
");

$mismatch=0;$checked=0;
foreach($rows as $r){
 $seg=mb_strtolower((string)($r['raw_segment']??''));
 $actual=mb_strtolower((string)($r['attribute_key']??''));
 if(!preg_match('/\b(?:q|r|req)\s*\d{1,2}\s*(tac(?:tics)?|str(?:ength)?|command|comm|mot(?:ivation)?)\b/iu',$seg,$m)) continue;
 $t=mb_strtolower($m[1]);
 $expected=str_starts_with($t,'tac')?'tactics':(str_starts_with($t,'str')?'strength':(($t==='command'||$t==='comm')?'command':(str_starts_with($t,'mot')?'motivation':'')));
 if($expected==='') continue;
 $checked++;
 if($actual!==$expected) $mismatch++;
}
echo PHP_EOL;
echo "Accepted shields met expliciete requirement gecontroleerd: {$checked}".PHP_EOL;
echo "Shield requirement mismatches: {$mismatch}".PHP_EOL;
echo "NB: +10 Fire/+10 Earth/+1 Fire zijn modifiers, geen requirement-fouten.".PHP_EOL;
