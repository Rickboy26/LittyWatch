<?php
declare(strict_types=1);
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $v, ?string $e=null): string { return strtolower($v); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $v, ?string $e=null): string { return strtoupper($v); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $v, ?string $e=null): int { return strlen($v); } }
if (!function_exists('mb_stripos')) { function mb_stripos(string $h,string $n,int $o=0,?string $e=null): int|false { return stripos($h,$n,$o); } }
if (!function_exists('mb_substr')) { function mb_substr(string $v,int $s,?int $l=null,?string $e=null): string { return $l===null?substr($v,$s):substr($v,$s,$l); } }
require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');
$failed=[];

$n=new \LittyWatch\Parser\SemanticNormalizer();
$idempotent=[
 'Deldrimor Armor Remnant',
 'Mysterious Armor Piece',
 'Primeval Armor Remnant',
];
foreach($idempotent as $v){
 $once=$n->normalize($v); $twice=$n->normalize($once);
 if($once!==$v || $twice!==$v)$failed[]=['idempotent'=>$v,'once'=>$once,'twice'=>$twice];
}

$x=new \LittyWatch\Parser\SharedOfferListExpander();
$got=$x->expand('q9 inscribable 2e/ea: WingedAxe, DualWingedAxe, HaloAxe');
$expected=['q9 inscribable 2e/ea WingedAxe','q9 inscribable 2e/ea DualWingedAxe','q9 inscribable 2e/ea HaloAxe'];
if($got!==$expected)$failed[]=['list_expand'=>$got];

$c=new \LittyWatch\Parser\Catalog(dirname(__DIR__) . '/app/Data');
$e=new \LittyWatch\Parser\ParserEngine($c);

foreach([
 ['WTB All ToT Bags','Trick-or-Treat Bag'],
 ['WTB Deld Hero armor','Deldrimor Armor Remnant'],
 ['WTS mysterious armor x3 5e each','Mysterious Armor Piece'],
 ['WTS Clockwork Scy','Clockwork Scythe'],
] as [$msg,$expectedItem]){
 $offers=$e->parse($msg); $names=array_map(fn($o)=>$o->item,$offers);
 if(!in_array($expectedItem,$names,true))$failed[]=['message'=>$msg,'expected'=>$expectedItem,'got'=>$names];
}

$offers=$e->parse('WTS q9 insc 2e/ea: WingedAxe, DualWingedAxe, HaloAxe');
$names=array_map(fn($o)=>$o->item,$offers);
foreach(['Winged Axe','Dual Winged Axe','Halo Axe'] as $expectedItem){
 if(!in_array($expectedItem,$names,true))$failed[]=['compact_list_missing'=>$expectedItem,'got'=>$names];
}

echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
