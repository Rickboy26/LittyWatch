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
$offers=$e->parse('WTS BDS | Air Q9/11 | Blood Q9/11 | SR Q10/11 | Illu Q9/10 | Smite Q9/10/13 | Dom Q9/12 | DF Q10 | Resto Q9/12 | Com Q10');
$got=[];
foreach($offers as $o){ $d=is_array($o)?$o:$o->toArray(); if(($d['item']??null)==='Bone Dragon Staff') $got[]=[($d['modifiers']['attribute']??null),($d['modifiers']['requirement']??null)]; }
$expected=[
 ['air magic','q9'],['air magic','q11'],['blood magic','q9'],['blood magic','q11'],['soul reaping','q10'],['soul reaping','q11'],
 ['illusion magic','q9'],['illusion magic','q10'],['smiting prayers','q9'],['smiting prayers','q10'],['smiting prayers','q13'],
 ['domination magic','q9'],['domination magic','q12'],['divine favor','q10'],['restoration magic','q9'],['restoration magic','q12'],['communing','q10']
];
$failed=[];
foreach($expected as $pair){ if(!in_array($pair,$got,true))$failed[]=$pair; }
$mini=$e->parse('WTB unded Gpriest');
$m0=$mini[0]??null;
$m0d=is_array($m0)?$m0:($m0?->toArray());
if(!$m0d || ($m0d['item']??null)!=='Miniature Ghostly Priest' || ($m0d['modifiers']['dedication']??null)!=='undedicated') $failed[]=['unded-mini',$m0d];
$mini2=$e->parse('WTS ded Gpriest');
$m1=$mini2[0]??null;
$m1d=is_array($m1)?$m1:($m1?->toArray());
if(!$m1d || ($m1d['item']??null)!=='Miniature Ghostly Priest' || ($m1d['modifiers']['dedication']??null)!=='dedicated') $failed[]=['ded-mini',$m1d];
echo json_encode(['ok'=>$failed===[],'count'=>count($got),'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
