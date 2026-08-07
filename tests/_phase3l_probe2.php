<?php
require dirname(__DIR__).'/bootstrap.php';
use LittyWatch\Parser\SemanticNormalizer;
use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;
$s=new SemanticNormalizer();
$p=new ParserEngine(new Catalog(dirname(__DIR__).'/app/Data'));
foreach(['Cupcakes WarSupps 20e','Slice of Pumpkin Pie Honeycombs 30e','Lunar Fortune Fortune\'s Fortune 25e'] as$m){
 echo "\n$m => ".$s->normalize($m)."\n";
 foreach($p->parse("WTS ".$m) as$o) echo $o->item." ".json_encode($o->price->toArray())."\n";
}
