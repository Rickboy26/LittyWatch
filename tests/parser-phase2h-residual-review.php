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
$cases=[
 ['WTS Stalkers 2e/ea',["Stalker's Ration"]],
 ['WTB Birds Eye q9 5e',['Birdseye']],
 ['WTS Echovald r9 Tac 45we/-2we 15e',['Echovald Shield']],
 ['WTB Cane 20/20 dom + illu',['Cane']],
 ["WTS Hog's Glut q9 tac/str",["Hog's Gluttony"]],
 ['WTB Map Set 1e',['Map Set']],
 ['WTS Diessa Set 120e',['Diessa Set']],
 ['WTB Seals 1e/ea',['Seal of the Dragon Empire']],
];
foreach($cases as [$msg,$expected]){
 $offers=$e->parse($msg); $names=array_values(array_unique(array_map(fn($o)=>$o->item,$offers)));
 foreach($expected as $name) if(!in_array($name,$names,true)) $failed[]=['message'=>$msg,'missing'=>$name,'got'=>$names];
}
foreach([
 'WTB Bowstrings',
 'WTB Strength of the Warrior',
 'WTB Soul Reaping +5 Sta',
 'WTB Tonic',
 'WTS 270e (6 times)',
 'WTB each fro',
] as $msg){
 $offers=$e->parse($msg);
 if($offers!==[]) $failed[]=['should_not_create_concrete_offer'=>$msg,'got'=>array_map(fn($o)=>$o->toArray(),$offers)];
}
$offers=$e->parse('WTB q9 tactics shield mods, volta user pm me');
foreach($offers as $o){ if($o->item==='Voltaic Spear') $failed[]=['false_voltaic_match'=>$o->toArray()]; }
echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
