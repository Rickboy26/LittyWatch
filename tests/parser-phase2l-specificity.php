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
 ['WTS OS Shinobi blade q10 15_en 8e','Shinobi Blade'],
 ['WTS OS Plagueb D q9 15_stanc 5e','Plagueborn Daggers'],
 ['WTS OS Chromium Shard q9 15_en 5e','Chromium Shards'],
 ["WTS Prenerf Strongroot's Shelte", "Strongroot's Shelter"],
 ['wts apt not att 19% x2','Aptitude Not Attitude'],
 ['WTS OS Plagueborn Staff - q9 Air Magic|20/10','Plagueborn Staff'],
];
foreach($cases as [$m,$want]){
 $o=$e->parse($m); $names=array_map(fn($x)=>$x->item,$o);
 if(!in_array($want,$names,true))$f[]=['missing',$m,$want,$names];
 foreach($o as $x) if($x->item===$want && $x->status!=='accepted') $f[]=['not_accepted',$m,$x->toArray()];
}
foreach([
 'WTB Ded Polar Bear',
 'WTS Raging Menzies (new FoW Green)',
 'WTB Mystical Summon Stone Gaki 1e/ea or for Tengu Guard Cons 1:1',
 'WTT -- Cons GoM for EoC/AoS ---',
] as $m){
 $o=$e->parse($m);
 foreach($o as $x){
   if(($m==='WTB Ded Polar Bear' && $x->item==='Miniature')
      || (str_contains($m,'Raging Menzies') && $x->item==='Unique item')
      || (str_contains($m,'Tengu Guard') && in_array($x->item,['Conset','Imperial Guard Reinforcement Order'],true))
      || (str_contains($m,'Cons GoM') && $x->item==='Conset')) $f[]=['false_generic',$m,$x->toArray()];
 }
}
echo json_encode(['ok'=>$f===[],'failed'=>$f],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($f===[]?0:1);
