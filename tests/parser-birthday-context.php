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
$offers=$e->parse('WTS BDay Present 1-7');
$names=array_values(array_unique(array_map(fn($o)=>$o->item,$offers)));
for($i=1;$i<=7;$i++){
 $suffix=match($i){1=>'st',2=>'nd',3=>'rd',default=>'th'};
 $expected="Xunlai Birthday Present {$i}{$suffix} Year";
 if(!in_array($expected,$names,true))$failed[]=['missing'=>$expected,'names'=>$names];
}
$v=$e->parse('WTS 57x Birthday Present Vouchers');
if(!$v || $v[0]->item!=='Xunlai Birthday Voucher')$failed[]=['voucher'=>$v?array_map(fn($o)=>$o->toArray(),$v):[]];
echo json_encode(['ok'=>$failed===[],'birthday_offers'=>count($names),'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
