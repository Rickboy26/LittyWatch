<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

$parser = new \LittyWatch\Parser\ParserEngine(new \LittyWatch\Parser\Catalog(dirname(__DIR__).'/app/Data'));
$one = static function(string $message, string $item) use ($parser): \LittyWatch\Parser\ParsedOffer {
    foreach ($parser->parse($message) as $offer) if ($offer->item === $item) return $offer;
    fwrite(STDERR,"Missing $item for: $message\n"); exit(1);
};
$assertUnit = static function($o,float $expected,string $label):void{
    if($o->price->unitEcto===null || abs((float)$o->price->unitEcto-$expected)>0.001){
        fwrite(STDERR,$label.' wrong: '.json_encode($o->price->toArray())."\n"); exit(1);
    }
};

// Armbrace regressions observed on production.
$o=$one('WTS armbraces 27e x6','Armbrace of Truth'); $assertUnit($o,27.0,'armbraces 27e x6');
$o=$one('WTB ARMS 26e/ea x43','Armbrace of Truth'); $assertUnit($o,26.0,'arms 26e/ea x43');
$o=$one('WTB ARMS 27e/ea 1750e = 64a','Armbrace of Truth'); $assertUnit($o,27.0,'explicit unit before conversion');
foreach(['WTB Arms 250e','WTB Arms for the best ones 12.5k'] as $message){
    $o=$one($message,'Armbrace of Truth');
    if($o->price->unitEcto!==null){fwrite(STDERR,"Ambiguous armbrace price trusted: $message\n");exit(1);}
}
$offers=$parser->parse('WTS ARMBRACES | BDS 17a');
foreach($offers as $o) if($o->item==='Armbrace of Truth' && $o->price->unitEcto!==null){fwrite(STDERR,"BDS price leaked to Armbrace\n");exit(1);}

// Stack semantics observed on Royal Gift production market.
$o=$one('WTB Royal Gifts 9a-stk','Royal Gift');
if($o->price->basis!=='stack'||$o->price->quantity!==250.0) {fwrite(STDERR,"9a-stk basis wrong\n");exit(1);} $assertUnit($o,243.0/250.0,'9a-stk');
$o=$one('WTS Royal Gifts 9a ea/stack','Royal Gift');
if($o->price->basis!=='stack'||$o->price->quantity!==250.0) {fwrite(STDERR,"ea/stack basis wrong\n");exit(1);} $assertUnit($o,243.0/250.0,'9a ea/stack');
$o=$one('WTS Royal Gift Stacks (x8) 8a','Royal Gift');
if($o->price->basis!=='stack_total'||$o->price->quantity!==2000.0) {fwrite(STDERR,"x8 stack total basis wrong: ".json_encode($o->price->toArray())."\n");exit(1);} $assertUnit($o,216.0/2000.0,'8 stacks for 8a');

echo "Phase 3E canonical price normalization OK\n";
