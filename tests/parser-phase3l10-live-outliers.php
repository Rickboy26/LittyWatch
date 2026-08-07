<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Market/MarketQualityService.php';
use LittyWatch\Market\MarketQualityService;
$rc=new ReflectionClass(MarketQualityService::class);
$svc=$rc->newInstanceWithoutConstructor();
$m=$rc->getMethod('recoverCanonicalPrice'); $m->setAccessible(true);
$cases=[
 ['Consets 9a','conset',9.0,'a',243.0,'each',1.0,0.972,'stack_inferred'],
 ['Conset 2e','conset',2.0,'e',2.0,'stack_inferred',250.0,2.0,'each_inferred'],
 ['Cons 9A/stack x14','conset',9.0,'a',243.0,'stack',250.0,0.972,'stack'],
 ['con stacks x3 9a/each','conset',9.0,'a',243.0,'each',1.0,0.972,'stack'],
];
foreach($cases as [$seg,$key,$amt,$cur,$ecto,$oldBasis,$qty,$want,$wantBasis]){
 $r=$m->invoke($svc,['raw_segment'=>$seg,'item_key'=>$key,'price_amount'=>$amt,'price_currency'=>$cur,'price_ecto'=>$ecto,'price_basis'=>$oldBasis,'quantity'=>$qty]);
 if(!$r || abs($r['unit']-$want)>0.001 || $r['basis']!==$wantBasis){fwrite(STDERR,"FAIL $seg ".json_encode($r)."\n");exit(1);}
}
foreach([
 ['gott 2e/','gift_of_the_traveler',2.0,'e',2.0,'stack_inferred',250.0],
 ['Tengu Support Flare 2e~','tengu_support_flare',2.0,'e',2.0,'stack_inferred',250.0],
] as [$seg,$key,$amt,$cur,$ecto,$basis,$qty]){
 $r=$m->invoke($svc,['raw_segment'=>$seg,'item_key'=>$key,'price_amount'=>$amt,'price_currency'=>$cur,'price_ecto'=>$ecto,'price_basis'=>$basis,'quantity'=>$qty]);
 if($r!==null){fwrite(STDERR,"FAIL ambiguous bare quote $seg ".json_encode($r)."\n");exit(1);}
}
echo "Phase 3L.10 live outlier semantics OK\n";
