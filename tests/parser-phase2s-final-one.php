<?php
declare(strict_types=1);
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $v, ?string $e=null): string { return strtolower($v); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $v, ?string $e=null): int { return strlen($v); } }
if (!function_exists('mb_stripos')) { function mb_stripos(string $h,string $n,int $o=0,?string $e=null): int|false { return stripos($h,$n,$o); } }
if (!function_exists('mb_substr')) { function mb_substr(string $v,int $s,?int $l=null,?string $e=null): string { return $l===null?substr($v,$s):substr($v,$s,$l); } }
require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');
$c=new \LittyWatch\Parser\Catalog(dirname(__DIR__) . '/app/Data');
$e=new \LittyWatch\Parser\ParserEngine($c);
$msg="WTB Vetaura's Harbinger/ Spear of the Hierophant// Mursaat Elementalist Polymock Piece/";
$offers=$e->parse($msg);
$failed=[];
$hit=array_values(array_filter($offers,fn($o)=>$o->item==='Spear of the Hierophant'));
if(!$hit || $hit[0]->confidence<.85) $failed[]=['missing_or_weak','offers'=>array_map(fn($o)=>$o->toArray(),$offers)];
foreach($offers as $o) if($o->item==='Spear') $failed[]=['generic_spear_shadow','offer'=>$o->toArray()];
echo json_encode(['ok'=>$failed===[],'failed'=>$failed,'offers'=>array_map(fn($o)=>$o->toArray(),$offers)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
