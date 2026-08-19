<?php
declare(strict_types=1);
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $v): string { return strtolower($v); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $v): int { return strlen($v); } }
require dirname(__DIR__).'/app/Parser/ParsedPrice.php';
require dirname(__DIR__).'/app/Parser/PriceMatcher.php';
require dirname(__DIR__).'/app/Parser/SmartSegmenter.php';
use LittyWatch\Parser\PriceMatcher;
use LittyWatch\Parser\SmartSegmenter;
$matcher=new PriceMatcher();$failed=[];
$cases=[
 ['5 GoTT 12e',12.0,'e','total',5.0,2.4],
 ['11 zkey for 7 ectos',7.0,'e','total',11.0,7.0/11.0],
 ['3:1 ecto',1.0,'e','ratio',3.0,1.0/3.0],
 ['2 stacks 18e/stk',18.0,'e','stack',250.0,18.0/250.0],
];
foreach($cases as [$input,$amount,$currency,$basis,$quantity,$unit]){$p=$matcher->parse($input);if($p->amount!==$amount||$p->currency!==$currency||$p->basis!==$basis||abs((float)$p->quantity-$quantity)>1e-9||abs((float)$p->unitEcto-$unit)>1e-9)$failed[]=['input'=>$input,'actual'=>$p->toArray()];}
foreach(['400gv','300gv','20/20','15^50','+30','-2we'] as $input){$p=$matcher->parse($input);if($p->amount!==null)$failed[]=['input'=>$input,'expected'=>'no money','actual'=>$p->toArray()];}
$segmenter=new SmartSegmenter();
$segments=$segmenter->split('A, B, C 4k each');$expected=['A 4k each','B 4k each','C 4k each'];if($segments!==$expected)$failed[]=['input'=>'A, B, C 4k each','expected'=>$expected,'actual'=>$segments];
$segments=$segmenter->split('Elite Tomes: Monk (12) Ele (6) Mes (10) 2e/ea');$expected=['12x Elite Monk Tome 2e each','6x Elite Elementalist Tome 2e each','10x Elite Mesmer Tome 2e each'];if($segments!==$expected)$failed[]=['input'=>'Elite Tomes quantities/shared price','expected'=>$expected,'actual'=>$segments];
echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit($failed===[]?0:1);
