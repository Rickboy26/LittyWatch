<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Market/MarketQualityService.php';
use LittyWatch\Market\MarketQualityService;
$rc=new ReflectionClass(MarketQualityService::class);
$svc=$rc->newInstanceWithoutConstructor();
$m=$rc->getMethod('shouldInvalidateStaleCanonicalPrice'); $m->setAccessible(true);
$bad=[
 ['gift_of_the_traveler','gott 2e/'],
 ['gift-of-the-traveler','nickgifts 11e'],
 ['tengu_support_flare','Tengu Support Flare 2e~'],
];
foreach($bad as [$key,$seg]){
 if(!$m->invoke($svc,['item_key'=>$key,'raw_segment'=>$seg])){fwrite(STDERR,"FAIL should invalidate $seg\n");exit(1);}
}
$good=[
 ['gift_of_the_traveler','GoTTs 2e ea'],
 ['gift-of-the-traveler','Gott Stack 28a'],
 ['tengu-support-flare','Tengu Support Flare 9a/stk'],
];
foreach($good as [$key,$seg]){
 if($m->invoke($svc,['item_key'=>$key,'raw_segment'=>$seg])){fwrite(STDERR,"FAIL should preserve explicit $seg\n");exit(1);}
}
echo "Phase 3L.11 stale mixed-basis invalidation OK\n";
