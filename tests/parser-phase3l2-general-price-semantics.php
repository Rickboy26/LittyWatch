<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$p=new ParserEngine(new Catalog(dirname(__DIR__).'/app/Data'));
$one=static function(string $m,string $item)use($p){foreach($p->parse($m) as$o)if($o->item===$item)return$o;fwrite(STDERR,"Missing $item: $m\n");exit(1);};
$unit=static function($o,float $x,string $label){if($o->price->unitEcto===null||abs($o->price->unitEcto-$x)>0.00001){fwrite(STDERR,$label.' '.json_encode($o->price->toArray())."\n");exit(1);}};

$unit($one('WTS Obsidian Shard 100e / stack','Obsidian Shard'),100/250,'explicit stack');
$unit($one('WTB Forget Me Not 3e/ea','Forget Me Not'),3.0,'explicit ea');
$unit($one('WTS Battle Isle Iced Tea 1e/ea','Battle Isle Iced Tea'),1.0,'explicit ea consumable');
$unit($one('WTB Shield 1e/ea','Shield'),1.0,'explicit ea generic');
$unit($one('WTS Primeval Armor Remnant 5e/ea','Primeval Armor Remnant'),5.0,'explicit ea armor');
$unit($one('WTB Armbraces 25e/ea','Armbrace of Truth'),25.0,'arm explicit ea');
$unit($one('WTS Candy Apple 17e/stk','Candy Apple'),17/250,'explicit stack candy');
$unit($one('WTB Sapphires 3:1e or for','Sapphire'),1/3,'ratio');
$unit($one('WTS Compasses 2=1e/S','Compass'),0.5,'quantity total equals');

$o=$one('WTS Red Rock Candy Candy 225-675e','Red Rock Candy');
if($o->price->basis!=='range'||$o->price->unitEcto!==null){fwrite(STDERR,"range trusted\n");exit(1);}

$o=$one('WTS Cupcakes / Eggs / Honeycombs 8e','Cupcake');
if($o->price->unitEcto!==null||$o->price->basis!=='uncertain'){fwrite(STDERR,"shared list trusted\n");exit(1);}

$o=$one('WTB Arms 250e','Armbrace of Truth');
if($o->price->unitEcto!==null){fwrite(STDERR,"arm 250 trusted\n");exit(1);}

$emerald=null;
foreach($p->parse('WTS Emerald Blade = 15a') as$c){if(str_starts_with($c->item,'Emerald Blade')){$emerald=$c;break;}}
if($emerald===null){fwrite(STDERR,"Missing Emerald Blade fallback\n");exit(1);}
$unit($emerald,405.0,'fallback single item');

echo "Phase 3L.2 general price semantics OK\n";
