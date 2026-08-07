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
 ["WTS Prenerf Strongroot's Shelter","Strongroot's Shelter"],
 ['WTS BUs 6a','Essence of Celerity'],
 ['wts el avatar of balthazar 9a','Everlasting Avatar of Balthazar Tonic'],
 ['WTS Honeycomb 4e ··· WTS Clovers 4e','Four-Leaf Clover'],
 ['WTS Cele Horse 10e','Miniature Celestial Horse'],
 ['WTB Aptitude no Attitude 3e','Aptitude Not Attitude'],
 ['WTB q4/q5^9 energy Estorage Foc','Focus'],
];
foreach($cases as [$m,$w]){ $o=$e->parse($m);$n=array_map(fn($x)=>$x->item,$o);if(!in_array($w,$n,true))$f[]=['missing',$m,$w,$n]; }
foreach([
 'WTS Bowstrings 2k|Icy Fiery.Crippling,Barbed',
 'WTS 2 staff heads energy+5',
 'wtb "of the ritualist" wand wrapping',
 'WTS +30hp for scythe. staff. axe.',
 'WTS Vampiric for Bow|Axe - 5k',
 'WTS Spear Def/Ench/Cruel/Shock',
 'WTS Insc: +15% (ench/stance) / 15^50 5k/ea',
 'WTB 8000 sweet points',
] as $m){ foreach($e->parse($m) as $x){ if(in_array($x->item,['Staff','Wand','Axe','Bow','Spear','Icy','Fiery','Cruel'],true) || $x->reason==='no_catalog_item')$f[]=['upgrade_noise',$m,$x->toArray()]; }}
$m='WTS OS Shinobi blade q10 15_en 8e,OS Plagueb D q9 15_stanc 5e,OS Chromium Shard q9 15_en 5e';
foreach($e->parse($m) as $x){if(preg_match('/^(?:en|stanc)\b/iu',$x->item))$f[]=['bad_split',$x->toArray()];}
echo json_encode(['ok'=>$f===[],'failed'=>$f],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit($f===[]?0:1);
