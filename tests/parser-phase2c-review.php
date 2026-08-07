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

$offers=$e->parse('WTS---- q11 Eternal Blade = 15a-----!!!!!');
$ok=false; foreach($offers as $o){ if($o->item==='Eternal Blade' && ($o->modifiers['requirement']??null)==='q11' && ($o->price->amount??null)==15.0)$ok=true; }
if(!$ok)$failed[]=['decorative'=>array_map(fn($o)=>$o->toArray(),$offers)];

$offers=$e->parse('WTB q 11 Eternal Blade');
$ok=false; foreach($offers as $o){ if($o->item==='Eternal Blade' && ($o->modifiers['requirement']??null)==='q11')$ok=true; }
if(!$ok)$failed[]=['spaced_q'=>array_map(fn($o)=>$o->toArray(),$offers)];

$offers=$e->parse('WTB q5-7 flatbows');
$reqs=[]; foreach($offers as $o){ if($o->item==='Flatbow')$reqs[]=$o->modifiers['requirement']??null; }
sort($reqs); if($reqs!==['q5','q6','q7'])$failed[]=['range'=>$reqs,'offers'=>array_map(fn($o)=>$o->toArray(),$offers)];

$offers=$e->parse('WTS Eternal Blade | q11 es q13 FC q13 spaw');
$pairs=[]; foreach($offers as $o){ if($o->item==='Eternal Blade')$pairs[]=[$o->modifiers['requirement']??null,$o->modifiers['attribute']??null]; }
$expected=[['q11','energy storage'],['q13','fast casting'],['q13','spawning power']];
foreach($expected as $pair){ if(!in_array($pair,$pairs,true))$failed[]=['variant_pair_missing'=>$pair,'pairs'=>$pairs]; }

$offers=$e->parse('WTB q');
if($offers!==[])$failed[]=['noise_q'=>array_map(fn($o)=>$o->toArray(),$offers)];
$offers=$e->parse('WTS for all');
if($offers!==[])$failed[]=['noise_for_all'=>array_map(fn($o)=>$o->toArray(),$offers)];

echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
