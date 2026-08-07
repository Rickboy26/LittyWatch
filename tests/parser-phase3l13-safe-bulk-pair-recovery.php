<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Market/MarketQualityService.php';
use LittyWatch\Market\MarketQualityService;

$rc=new ReflectionClass(MarketQualityService::class);
$svc=$rc->newInstanceWithoutConstructor();
$m=$rc->getMethod('recoverCanonicalPrice');
$m->setAccessible(true);

$cases=[
 ['GOTT STACK -27A/ 2-53A','gift_of_the_traveler',53.0,'a',1431.0,'uncertain',null,2.916,'stack'],
 ['Gott Stack 28a','gift_of_the_traveler',28.0,'a',756.0,'uncertain',null,3.024,'stack'],
];
foreach($cases as [$seg,$key,$amt,$cur,$ecto,$basis,$qty,$want,$wantBasis]){
 $r=$m->invoke($svc,[
  'raw_segment'=>$seg,'item_key'=>$key,'price_amount'=>$amt,'price_currency'=>$cur,
  'price_ecto'=>$ecto,'price_basis'=>$basis,'quantity'=>$qty
 ]);
 if(!$r || abs($r['unit']-$want)>0.001 || $r['basis']!==$wantBasis){
   fwrite(STDERR,"FAIL $seg ".json_encode($r)."\n"); exit(1);
 }
}

// Bad bulk math must stay unresolved.
$r=$m->invoke($svc,[
 'raw_segment'=>'GOTT STACK -27A/ 2-5A','item_key'=>'gift_of_the_traveler',
 'price_amount'=>5.0,'price_currency'=>'a','price_ecto'=>135.0,'price_basis'=>'uncertain','quantity'=>null
]);
if($r!==null){fwrite(STDERR,"FAIL unsafe bulk pair ".json_encode($r)."\n");exit(1);}

echo "Phase 3L.13 safe bulk-pair recovery OK\n";
