<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

$parser = new \LittyWatch\Parser\ParserEngine(new \LittyWatch\Parser\Catalog(dirname(__DIR__).'/app/Data'));

$one = static function(string $message, string $item) use ($parser): \LittyWatch\Parser\ParsedOffer {
    foreach ($parser->parse($message) as $offer) if ($offer->item === $item) return $offer;
    fwrite(STDERR,"Missing $item for: $message\n"); exit(1);
};

$o=$one('WTS armbraces 27e x6','Armbrace of Truth');
if($o->price->basis!=='each'||$o->price->quantity!==6.0||abs((float)$o->price->unitEcto-27.0)>0.001){fwrite(STDERR,"27e x6 semantics wrong: ".json_encode($o->price->toArray())."\n");exit(1);}

$o=$one('WTB ARMS 26e/ea x43','Armbrace of Truth');
if($o->price->basis!=='each'||$o->price->quantity!==43.0||abs((float)$o->price->unitEcto-26.0)>0.001){fwrite(STDERR,"26e/ea x43 semantics wrong\n");exit(1);}

$o=$one('WTB ARMS 27e/ea 1750e = 64a','Armbrace of Truth');
if(abs((float)$o->price->unitEcto-27.0)>0.001){fwrite(STDERR,"conversion overrode unit price\n");exit(1);}

$o=$one('WTB Arms 250e','Armbrace of Truth');
if($o->price->basis!=='uncertain'||$o->price->unitEcto!==null){fwrite(STDERR,"ambiguous Arms 250e should not be trusted unit price\n");exit(1);}

$o=$one('WTS BDS 30a','Bone Dragon Staff');
if($o->price->basis!=='each_inferred'||abs((float)$o->price->unitEcto-810.0)>0.001){fwrite(STDERR,"BDS 30a lost per-item semantics\n");exit(1);}

$offers=$parser->parse('WTS ARMBRACES | BDS 17a');
foreach($offers as $offer){
    if($offer->item==='Armbrace of Truth' && $offer->price->amount!==null){fwrite(STDERR,"segment-local price leakage remains\n");exit(1);}
}

if(lw_market_price_for_item('Armbrace of Truth',810.0)!=='810e'){fwrite(STDERR,"Armbrace display must stay ecto-primary\n");exit(1);}
if(strpos(lw_market_price_for_item('Bone Dragon Staff',810.0),'a')===false){fwrite(STDERR,"High value normal item should prefer armbraces\n");exit(1);}

echo "Phase 3D price semantics OK\n";
