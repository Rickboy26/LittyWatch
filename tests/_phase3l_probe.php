<?php
require dirname(__DIR__).'/bootstrap.php';
use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;
$p=new ParserEngine(new Catalog(dirname(__DIR__).'/app/Data'));
foreach([
'WTS Star of Transference 1e-ea',
'WTS Cupcakes 8e',
'WTS War Supplies 9e',
'WTS Lockpicks 20e',
'WTB Zaishen Key 1.3e/Flames 0.5e',
'WTS Black Dye 3/1e',
'WTB gott 5/11E',
'WTS black dye 5=40plat',
'WTS Cupcakes / Eggs / Honeycombs 8e',
'WTS Cupcakes 8e, Eggs 7e',
'WTS Conset 2e',
] as $m){
 echo "\n$m\n";
 foreach($p->parse($m) as $o){
   echo $o->item." | ".json_encode($o->price->toArray(),JSON_UNESCAPED_UNICODE)."\n";
 }
}
