<?php
require dirname(__DIR__).'/bootstrap.php';
use LittyWatch\Parser\Catalog; use LittyWatch\Parser\ParserEngine;
$p=new ParserEngine(new Catalog(dirname(__DIR__).'/app/Data'));
foreach(['WTS Soulbreaker r13=4a','WTS Memory 20%=1e','WTS Emerald Blade = 15a','WTS Asterius Scythe = 8e'] as$m){
 echo "\n$m\n"; foreach($p->parse($m) as$o) echo $o->item.' | '.$o->segment.' | '.json_encode($o->price->toArray())."\n";
}
