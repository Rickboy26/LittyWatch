<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Market/MarketQualityService.php';
use LittyWatch\Market\MarketQualityService;

$rc=new ReflectionClass(MarketQualityService::class);
$svc=$rc->newInstanceWithoutConstructor();
$m=$rc->getMethod('shouldInvalidateStaleCanonicalPrice');
$m->setAccessible(true);

$yes=[
 ['gift_of_the_traveler','gott 2e, materials 2k s'],
 ['gift_of_the_traveler','gott 2e/'],
 ['tengu_support_flare','Tengu Support Flare 2e~'],
];
foreach($yes as [$key,$seg]){
 $row=['item_key'=>$key,'raw_segment'=>$seg,'price_quality_reason'=>''];
 if($m->invoke($svc,$row)!==true){fwrite(STDERR,"FAIL should invalidate: $seg\n");exit(1);}
}

$no=[
 ['gift_of_the_traveler','GoTTs 2e ea'],
 ['gift_of_the_traveler','Gott Stack 28a'],
 ['tengu_support_flare','Tengu Support Flare 9a/stk'],
 ['conset','Conset 2e'],
 ['conset','Cons 9A/stack x14'],
 ['lockpick','lockpicks 21e ea'],
 ['lockpick','lockpick 100k'],
 ['conset','consets 13e'], // Phase 3L.15: live currency semantics resolve this.
 ['lockpick','lockpicks 100k'], // Phase 3L.15: catalog stack semantics resolve this.
];
foreach($no as [$key,$seg]){
 $row=['item_key'=>$key,'raw_segment'=>$seg,'price_quality_reason'=>''];
 if($m->invoke($svc,$row)!==false){fwrite(STDERR,"FAIL should keep: $seg\n");exit(1);}
}

echo "Phase 3L.12 bare plural + clause invalidation OK\n";
