<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$p=new ParserEngine(new Catalog(dirname(__DIR__).'/app/Data'));
$one=static function(string $m,string $item)use($p){foreach($p->parse($m) as$o)if($o->item===$item)return$o;fwrite(STDERR,"Missing $item: $m\n");exit(1);};
$unit=static function($o,float $x,string $label){if($o->price->unitEcto===null||abs($o->price->unitEcto-$x)>0.00001){fwrite(STDERR,$label.' '.json_encode($o->price->toArray())."\n");exit(1);}};

// Current workbench regressions: these must become trusted after a full reparse.
$unit($one('WTS Warrior Tome 2e/ea','Warrior Tome'),2.0,'warrior tome');
$unit($one('WTS Memory 20%>1e 2xElite','Memory'),1.0,'memory');
$unit($one('WTS AnA 19%-1e/ea','Aptitude not Attitude'),1.0,'ana');
$unit($one('WTS DSR 6A-','Dhuum\'s Soul Reaper'),162.0,'dsr');
$unit($one('WTS Rift Warden 25a','Rift Warden'),675.0,'rift warden');
$unit($one('WTB Sapphires 3:1e or for','Sapphire'),1/3,'sapphire ratio');
$unit($one('WTS Gaki 45a','Mystical Summoning Stone (Gaki)'),1215.0,'gaki bare each');
$unit($one('WTS Stalkers 50e/stk','Stalker\'s Ration'),50/250,'stalker stack');

// Deliberately stay uncertain: ambiguous commodity/shared-list/range observations.
foreach([
 ['WTS Soup 50e','Soup'],
 ['WTS Cupcakes WarSupps 20e','Cupcake'],
 ['WTS Slice of Pumpkin Pie Honeycombs 30e','Slice of Pumpkin Pie'],
 ['WTS Red Rock Candy Candy 225-675e','Red Rock Candy'],
] as [$msg,$item]) {
    $o=$one($msg,$item);
    if($o->price->unitEcto!==null){fwrite(STDERR,"Ambiguous price trusted: $msg\n");exit(1);}
}

echo "Phase 3L.3 uncertain cleanup OK\n";
