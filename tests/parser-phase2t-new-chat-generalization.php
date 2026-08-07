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
$failed=[];
$must=[
 ['WTS CC Q11 Water///Hourglass Q9',['Celestial Compass','Hourglass Staff']],
 ["wts q9 Dom Quicksilver ; q12 Dom/q10 FC Peacocks Wrath ; q9 Plat. Longbow ; q9 Igneous Blade pst",['Quicksilver',"Peacock's Wrath"]],
 ['WTB 40elonian leather squares 1',['Elonian Leather Square']],
 ['WTB Feathers x25 stacks',['Feather']],
 ['WTB Mysterious Summonig Stones',['Mysterious Summoning Stone']],
 ['WTS STRATEGIST 37A /// HERO 12A',["Strategist's Zaishen Strongbox","Hero's Strongbox"]],
 ['WTB Stars of Transf 1e-ea',['Star of Transference']],
 ['WTB Stars of Transference 1e-ea',['Star of Transference']],
 ['WTB unded ghostly priest',['Miniature Ghostly Priest']],
];
foreach($must as [$msg,$wants]){
 $offers=$e->parse($msg); $names=array_map(fn($o)=>$o->item,$offers);
 foreach($wants as $want){
  $hits=array_values(array_filter($offers,fn($o)=>$o->item===$want));
  if(!$hits || $hits[0]->confidence<.85) $failed[]=['missing_or_weak'=>$want,'msg'=>$msg,'offers'=>array_map(fn($o)=>$o->toArray(),$offers)];
 }
 foreach($offers as $o) if($o->reason==='no_catalog_item') $failed[]=['unexpected_no_catalog'=>$o->item,'msg'=>$msg];
}
foreach([
 'WTB 38 ritualist and 6 warrior',
 'wts q9inscr weaps,mods:gwmarket',
 'WTB alcohol stacks (no Kegs)',
 'wts gold noid 1K/U',
] as $msg){
 $offers=$e->parse($msg);
 foreach($offers as $o) if($o->reason==='no_catalog_item') $failed[]=['context_leaked_to_review'=>$o->item,'msg'=>$msg,'offers'=>array_map(fn($x)=>$x->toArray(),$offers)];
}
echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
