<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use LittyWatch\Market\MarketQualityService;
$ref=new ReflectionClass(MarketQualityService::class); $svc=$ref->newInstanceWithoutConstructor();
$m=$ref->getMethod('recoverCanonicalPrice'); $m->setAccessible(true);
$cases=[
 ['lockpick','Lockpick stacks 22e/ea',22,'e',22,22/250,'stack'],
 ['lockpick','Lockpicks 20e/ea 50x',20,'e',20,20/250,'stack_inferred'],
 ['candy_apple','Candy Apple stacks 17e/ea 18x',17,'e',17,17/250,'stack'],
 ['zaishen_key','Zaishen Key Stacks 15a/ea (x15)',15,'a',405,405/250,'stack'],
 ['gift_of_the_traveler','Gott Stacks (x10) 26a/each 10=250a',250,'a',6750,702/250,'stack'],
];
foreach($cases as [$key,$segment,$amount,$currency,$ecto,$expected,$expectedBasis]){
 $r=$m->invoke($svc,['item_key'=>$key,'price_basis'=>'each','price_amount'=>$amount,'price_currency'=>$currency,'price_ecto'=>$ecto,'quantity'=>null,'raw_segment'=>$segment]);
 if(!is_array($r)||abs((float)$r['unit']-$expected)>0.00001||$r['basis']!==$expectedBasis){
  fwrite(STDERR,"FAIL $segment: ".var_export($r,true)." expected $expected\n"); exit(1);
 }
}
echo "Phase 3L.8 explicit stack-lot semantics OK\n";
