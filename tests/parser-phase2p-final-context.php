<?php
declare(strict_types=1);
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $v, ?string $e=null): string { return strtolower($v); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $v, ?string $e=null): string { return strtoupper($v); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $v, ?string $e=null): int { return strlen($v); } }
if (!function_exists('mb_stripos')) { function mb_stripos(string $h,string $n,int $o=0,?string $e=null): int|false { return stripos($h,$n,$o); } }
if (!function_exists('mb_substr')) { function mb_substr(string $v,int $s,?int $l=null,?string $e=null): string { return $l===null?substr($v,$s):substr($v,$s,$l); } }
require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');
$c=new \LittyWatch\Parser\Catalog(dirname(__DIR__) . '/app/Data');
$e=new \LittyWatch\Parser\ParserEngine($c);
$f=[];
$offers=$e->parse('WTS Spear Def/Ench/Cruel/Shock');
$names=array_map(fn($x)=>$x->item,$offers);
foreach(['Spear Grip of Defense','Spear Grip of Enchanting','Cruel Spearhead','Shocking Spearhead'] as $want){if(!in_array($want,$names,true))$f[]=['missing_component',$want,$names];}
foreach($offers as $o){if($o->reason==='low_confidence')$f[]=['unexpected_low',$o->toArray()];}
$offers=$e->parse('WTB Q7/Q8 GOLD OLDSCHOOL WEAPONS/SHIELDS. Any item. Show me what you got.');
foreach($offers as $o){if($o->item==='Gift of the Traveler')$f[]=['false_gott',$o->toArray()];}
$offers=$e->parse('wtb large equipment staff');
if($offers===[] || $offers[0]->item!=='Large Equipment Pack')$f[]=['large_equipment_typo',array_map(fn($x)=>$x->toArray(),$offers)];
$offers=$e->parse('WTS Mods +5^50, +10 vs Blunt, Cold, +5/-20% | Zealous, Enchant Scythe | Enchant Spear | Zealous, Vamp Bow');
foreach($offers as $o){if($o->reason==='low_confidence' && in_array($o->item,['Bow','Cruel'],true))$f[]=['mod_shadow',$o->toArray()];}
echo json_encode(['ok'=>$f===[],'failed'=>$f],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($f===[]?0:1);
