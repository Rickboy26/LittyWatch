<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Market/MarketQualityService.php';
use LittyWatch\Market\MarketQualityService;
$rc=new ReflectionClass(MarketQualityService::class);
$svc=$rc->newInstanceWithoutConstructor();
$m=$rc->getMethod('recoverCanonicalPrice');$m->setAccessible(true);
$cases=[
 ['Zaishen Key 1.3e/ea or 12a/stack','zaishen_key',12.0,'a',324.0,1.3,'each'],
 ["Lunar Fortune Fortune's Fortune 20e/stk 7=5a",'lunar_fortune',5.0,'a',135.0,0.08,'stack'],
 ['Gott Stacks (x10) 26a/each 10=250a/','gift_of_the_traveler',250.0,'a',6750.0,2.808,'stack'],
 ['GoTTs 2e ea','gift_of_the_traveler',2.0,'e',2.0,2.0,'each'],
];
foreach($cases as [$seg,$key,$amt,$cur,$ecto,$want,$basis]){
 $r=$m->invoke($svc,['raw_segment'=>$seg,'item_key'=>$key,'price_amount'=>$amt,'price_currency'=>$cur,'price_ecto'=>$ecto,'price_basis'=>'uncertain','quantity'=>null]);
 if(!$r || abs($r['unit']-$want)>0.001 || $r['basis']!==$basis){fwrite(STDERR,"FAIL $seg ".json_encode($r)."\n");exit(1);} }
// Bare mixed-basis GoTT quote must not be inferred from catalog default.
$r=$m->invoke($svc,['raw_segment'=>'gott 30a','item_key'=>'gift_of_the_traveler','price_amount'=>30,'price_currency'=>'a','price_ecto'=>810,'price_basis'=>'uncertain','quantity'=>null]);
if($r!==null){fwrite(STDERR,"FAIL bare gott should remain unresolved\n");exit(1);} 
echo "Phase 3L.9 outlier semantics OK\n";
