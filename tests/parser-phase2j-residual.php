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
 ['WTS Raging Menzies (new FoW Green)','Raging Menzies'],
 ['WTS Elegant Menzies 100e Black','Elegant Menzies'],
 ["WTS Spiders Gluttony q10 Lead Insc PM offer","Spider's Gluttony"],
 ['WTS Padraic','Padraic'],
 ['wtb Gaki Polymock piece','Gaki Polymock Piece'],
 ['WTS q10 inscrib Eternal Recurve','Eternal Bow'],
 ['wtb gold coins 6e each','Gold Zaishen Coin'],
 ['wtb con stacks x3 9a/each','Conset'],
 ['WTB Mini Althea & Undead (Zombie) Rurik 50a/ea','Miniature Undead Prince Rurik'],
];
foreach($cases as [$m,$w]) { $o=$e->parse($m); $n=array_map(fn($x)=>$x->item,$o); if(!in_array($w,$n,true))$f[]=[$m,$w,$n]; }
foreach(['WTS Mods +5^50, +10 vs Blunt, Cold, +5/-20%','WTS +5 Strength of the Warrior','WTB Mod Soul Reaping +5 Sta','WTS Cons 9A/stack x14 - VS Q9','WTS Normal Tomes 500g/ea no Dervish'] as $m){
 foreach($e->parse($m) as $x) if($x->reason==='no_catalog_item') $f[]=['should_not_no_catalog',$m,$x->toArray()];
}
echo json_encode(['ok'=>$f===[],'failed'=>$f],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL; exit($f===[]?0:1);
