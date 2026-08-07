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
$e=new \LittyWatch\Parser\ParserEngine($c); $f=[];
$cases=[
 ['wtb "of the ritualist" wand wra','Wand Wrapping of the Ritualist'],
 ['WTS Hale and Hearty (Tips)','Hale and Hearty'],
 ['WTS Hajok\'s Prism Urkal\'s Kamas',"Hajok's Prism"],
 ['WTS Hajok\'s Prism Urkal\'s Kamas',"Urkal's Kamas"],
 ['WTS ithas bow q9 15^50','Ithas Bow'],
 ['WTB q9 Eternal Flat Bow / Ded Polar Bear','Eternal Bow'],
 ['WTS mesmer wand wrap 1e/ // aptitude focus core 2e','Focus Core of Aptitude'],
 ['WTS patron+5 for wand 4k, swift for focus HCT10% 8k','Focus Core of Swiftness'],
];
foreach($cases as [$msg,$want]){
 $o=$e->parse($msg); $hit=null; foreach($o as $x) if($x->item===$want){$hit=$x;break;}
 if(!$hit) $f[]=['missing',$msg,$want,array_map(fn($x)=>$x->item,$o)];
 elseif($hit->status!=='accepted') $f[]=['not_accepted',$msg,$hit->toArray()];
}
// No review-level base weapon may survive explicit component text.
foreach([
 'WTS Mods +5^50 | Zealous, Vamp Bow',
 'wtb "of the ritualist" wand wra',
 'WTS mesmer wand wrap 1e',
] as $msg){
 foreach($e->parse($msg) as $x) if($x->status==='review' && in_array($x->item,['Bow','Wand','Focus item','Focus'],true)) $f[]=['component_shadow',$msg,$x->toArray()];
}
// Reflection covers DB-learned generic rows: explicit Q requirement promotes them.
$ref=new ReflectionClass($e); $m=$ref->getMethod('promoteExplicitGenericRequirements'); $m->setAccessible(true);
$none=new \LittyWatch\Parser\ParsedPrice(null,null,null,'unknown',null,null,null);
$fake=[new \LittyWatch\Parser\ParsedOffer('buy','Bow','bow',['requirement'=>'q8'],$none,0.80,'review','low_confidence','Q8 Bows')];
$prom=$m->invoke($e,$fake);
if($prom[0]->status!=='accepted' || $prom[0]->confidence<0.86) $f[]=['generic_q_not_promoted',$prom[0]->toArray()];

echo json_encode(['ok'=>$f===[],'failed'=>$f],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($f===[]?0:1);
