<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$p=new ParserEngine(new Catalog(dirname(__DIR__).'/app/Data'));
$one=static function(string $m,string $item)use($p){foreach($p->parse($m) as$o)if($o->item===$item)return$o;fwrite(STDERR,"Missing $item: $m\n");exit(1);};
$unit=static function($o,float $x,string $label){if($o->price->unitEcto===null||abs($o->price->unitEcto-$x)>0.00001){fwrite(STDERR,$label.' '.json_encode($o->price->toArray())."\n");exit(1);}};

$unit($one('WTS Conset 2e','Conset'),2.0,'conset each');
$unit($one('WTS Conset 9a','Conset'),(9*27)/250,'conset stack');
$unit($one('WTS Conset 2e/ea','Conset'),2.0,'conset explicit each');
$unit($one('WTS Conset 9a/stk','Conset'),(9*27)/250,'conset explicit stack');

$unit($one('WTS Emerald Blade = 15a','Emerald Blade'),405.0,'emerald equals');
$unit($one('WTS Asterius Scythe = 8e','Asterius Scythe'),8.0,'asterius equals');
$unit($one('WTS Memory 20%=1e','Memory'),1.0,'memory equals');
$soul=null; foreach($p->parse('WTS Soulbreaker r13=4a') as $candidate){ if(str_starts_with($candidate->item,'Soulbreaker')){$soul=$candidate;break;}}
if($soul===null){fwrite(STDERR,"Missing Soulbreaker\n");exit(1);}
$unit($soul,108.0,'soulbreaker equals');

// True currency conversion must stay conversion-safe.
$o=$one('WTB ARMS 27e/ea 1750e = 64a','Armbrace of Truth');
$unit($o,27.0,'armbrace explicit each before conversion');

echo "Phase 3L.1 conset/equals hotfix OK\n";
