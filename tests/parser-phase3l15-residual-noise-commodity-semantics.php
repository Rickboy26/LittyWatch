<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;
use LittyWatch\Parser\ReviewCandidateClassifier;
use LittyWatch\Market\MarketQualityService;

$catalog=new Catalog(dirname(__DIR__).'/app/Data');
$parser=new ParserEngine($catalog);

$expect=function(string $message,string $want)use($parser):void{
    $offers=$parser->parse($message);
    foreach($offers as $offer){
        if (mb_strtolower($offer->item)===mb_strtolower($want)) return;
    }
    fwrite(STDERR,"FAIL $message expected $want got ".implode(', ',array_map(fn($o)=>$o->item,$offers))."\n"); exit(1);
};

$expect('WTS Delicous Cake 0,6e/ea','Delicious Cake');
$expect('WTB stack of bones','Bone');

$review=new ReviewCandidateClassifier(null);
foreach([
 ['ranger / monk / para / sin / mes / necro','ranger / monk / para / sin / mes / necro'],
 ['ranger / mo','ranger / mo'],
 ['all profess','all profess'],
 ['all Q9+inscribable','all Q9+inscribable'],
 ['1 hours)','1 hours)'],
 ['Running From Kaening Centre to Shrio (factions finish in','Running From Kaening Centre to Shrio (factions finish in'],
 ['Scrolls 3a or','Scrolls 3a or 2=5a'],
] as [$candidate,$segment]){
    $r=$review->classify($candidate,$segment);
    if($r['kind']==='item'){fwrite(STDERR,"FAIL residual review item: $candidate\n");exit(1);}
}

$rc=new ReflectionClass(MarketQualityService::class);
$svc=$rc->newInstanceWithoutConstructor();
$recover=$rc->getMethod('recoverCanonicalPrice'); $recover->setAccessible(true);
$invalidate=$rc->getMethod('shouldInvalidateStaleCanonicalPrice'); $invalidate->setAccessible(true);

$cases=[
 ['lockpick','LOCKPICKS 20E',20.0,'e',20.0,20.0/250.0,'stack_inferred'],
 ['conset','CONSETS 9A',9.0,'a',243.0,243.0/250.0,'stack_inferred'],
 ['conset','Cons 10a',10.0,'a',270.0,270.0/250.0,'stack_inferred'],
 ['conset','consets 13e',13.0,'e',13.0,13.0,'each_inferred'],
];
foreach($cases as [$key,$seg,$amt,$cur,$ecto,$want,$basis]){
    $row=['item_key'=>$key,'raw_segment'=>$seg,'price_amount'=>$amt,'price_currency'=>$cur,'price_ecto'=>$ecto,'price_basis'=>'uncertain','quantity'=>null,'price_quality_reason'=>''];
    $r=$recover->invoke($svc,$row);
    if(!$r || abs((float)$r['unit']-$want)>0.00001 || $r['basis']!==$basis){
        fwrite(STDERR,"FAIL recovery $seg ".json_encode($r)."\n");exit(1);
    }
    if($invalidate->invoke($svc,$row)!==false){
        fwrite(STDERR,"FAIL invalidated $seg\n");exit(1);
    }
}
echo "Phase 3L.15 residual noise + commodity semantics OK\n";
