<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$p=new ParserEngine(new Catalog(dirname(__DIR__).'/app/Data'));
$one=static function(string $m,string $item)use($p){foreach($p->parse($m) as$o)if($o->item===$item)return$o;fwrite(STDERR,"Missing $item: $m\n");exit(1);};
$unit=static function($o,float $x,string $label){if($o->price->unitEcto===null||abs($o->price->unitEcto-$x)>0.00001){fwrite(STDERR,$label.' '.json_encode($o->price->toArray())."\n");exit(1);}};

$unit($one('WTS Star of Transference 1e-ea','Star of Transference'),1.0,'star each');
$unit($one('WTS Cupcakes 8e','Cupcake'),8/250,'cupcake stack');
$unit($one('WTS War Supplies 9e','War Supplies'),9/250,'warsup stack');
$unit($one('WTS Lockpicks 20e','Lockpick'),20/250,'lockpick stack');
$unit($one('WTS Black Dye 3/1e','Black Dye'),1/3,'3/1e');
$unit($one('WTB gott 5/11E','Gift of the Traveler'),11/5,'gott quantity total');
$unit($one('WTS black dye 5=40plat','Black Dye'),(40/15)/5,'plat total');
$o=$one('WTS Cupcakes / Eggs / Honeycombs 8e','Cupcake');
if($o->price->unitEcto!==null||$o->price->basis!=='uncertain'){fwrite(STDERR,"shared slash price trusted\n");exit(1);}
$o=$one('WTS Cupcakes WarSupps 20e','Cupcake');
if($o->price->unitEcto!==null||$o->price->basis!=='uncertain'){fwrite(STDERR,"compact shared price trusted\n");exit(1);}
$o=$one('WTS Conset 2e','Conset');
if($o->price->unitEcto!==null){fwrite(STDERR,"ambiguous conset promoted\n");exit(1);}
echo "Phase 3L price pattern generalization OK\n";
