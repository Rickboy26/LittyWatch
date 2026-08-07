<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use LittyWatch\Market\MarketQualityService;

$ref=new ReflectionClass(MarketQualityService::class);
$svc=$ref->newInstanceWithoutConstructor();
$recover=$ref->getMethod('recoverCanonicalPrice'); $recover->setAccessible(true);
$semantic=$ref->getMethod('semanticStatus'); $semantic->setAccessible(true);

$case=static function(string $item,string $segment,float $amount,string $currency,float $ecto,?float $unit,?string $basis)use($svc,$recover,$semantic):void{
    $r=$recover->invoke($svc,[
        'item_key'=>$item,'price_basis'=>'uncertain','price_amount'=>$amount,'price_currency'=>$currency,
        'price_ecto'=>$ecto,'quantity'=>null,'raw_segment'=>$segment,
    ]);
    if ($unit===null) {
        if ($r!==null) { fwrite(STDERR,"$segment incorrectly recovered\n"); exit(1); }
        return;
    }
    if (!is_array($r) || abs((float)$r['unit']-$unit)>0.00001 || ($r['basis']??null)!==$basis) {
        fwrite(STDERR,"$segment recovery mismatch: ".var_export($r,true)."\n"); exit(1);
    }
    [$status,$reason]=$semantic->invoke($svc,[
        'trade_type'=>'sell','item_key'=>$item,'price_amount'=>$amount,'price_currency'=>$currency,
        'unit_price_ecto'=>$r['unit'],'price_basis'=>$r['basis'],'price_quality_reason'=>null,
    ]);
    if ($status!=='trusted') { fwrite(STDERR,"$segment remained $status ($reason)\n"); exit(1); }
};

// Concrete non-commodities: bare single quote means per item.
$case('rift_warden','Rift Warden 25a',25,'a',675,675,'each_inferred');
$case('ghostly_hero','ghostly hero 725a',725,'a',19575,19575,'each_inferred');
$case('mallyx','MALLYX 50e',50,'e',50,50,'each_inferred');
$case('miniature_polar_bear','Polar 100a',100,'a',2700,2700,'each_inferred');
$case('cane','Cane 65a',65,'a',1755,1755,'each_inferred');

// Catalog-declared stack quotes: naked market price is one full stack.
$case('cupcake','Cupcakes 8e',8,'e',8,8/250,'stack_inferred');
$case('lunar_fortune','Lunar Fortune Fortune Fortunes 20e',20,'e',20,20/250,'stack_inferred');
$case('four_leaf_clover','Four-Leaf Clover 15e',15,'e',15,15/250,'stack_inferred');
$case('slice_of_pumpkin_pie','Slice of Pumpkin Pie 20e',20,'e',20,20/250,'stack_inferred');

// Explicit each catalog quote.
$case('gold_zaishen_coin','gold coin 5e (x40)',5,'e',5,5,'each_inferred');

// Deliberately ambiguous cases remain uncertain.
$case('red_rock_candy','Red Rock Candy Candy 225-675e',675,'e',675,null,null);
$case('cupcake','Cupcakes / Eggs / Honeycombs 8e',8,'e',8,null,null);
// Phase 3L.13 promotes this formerly ambiguous pattern via validated bulk-pair semantics.
$case('gift_of_the_traveler','GOTT STACK -27A/ 2-53A',53,'a',1431,729/250,'stack');
$case('soup','Soup 50e',50,'e',50,null,null);
$case('compass','Compasses 20e',20,'e',20,null,null);

echo "Phase 3L.7 canonical catalog-key bare quote recovery OK\n";
