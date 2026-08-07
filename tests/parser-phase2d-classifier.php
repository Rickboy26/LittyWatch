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
$failed=[];

foreach([
 'WTB Q8 OS WEAPONS & SHIELDS - pm me',
 'WTB hero armor upgrades all except cloth',
 'WTS Storage sale! PM for inventory list >600 Weapons|Mods|Skins|OS|Greens|Tomes|Dcores|and more!',
 'WTB 750 drunk points',
 'WTS 2,500 RP to trim your guild',
 'Can someone help me get Together As One (ranger elite) dont have item <3',
 'WTB 7E=100K X7',
] as $msg) {
    $offers=$e->parse($msg);
    if($offers!==[]) $failed[]=['should_not_create_item'=>$msg,'offers'=>array_map(fn($o)=>$o->toArray(),$offers)];
}

// Ampersands inside quoted official names must stay intact.
$g=new \LittyWatch\Parser\GrammarSegmenter();
$segments=$g->split('"Strength & Honor" & Shield Handle of Fortitude');
if($segments!==['"Strength & Honor"','Shield Handle of Fortitude']) $failed[]=['quoted_ampersand'=>$segments];

// Verbose trade intent is normalized before offer splitting.
$offers=$e->parse('want to buy Eternal Blade');
$ok=false; foreach($offers as $o){ if($o->item==='Eternal Blade' && $o->tradeType==='buy')$ok=true; }
if(!$ok)$failed[]=['want_to_buy'=>array_map(fn($o)=>$o->toArray(),$offers)];

echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
