<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__).'/app');
if(!extension_loaded('pdo_sqlite')){echo "Phase 3X contextual offer segmentation: SKIP (pdo_sqlite missing)\n";exit(0);}

use LittyWatch\Knowledge\Schema;
use LittyWatch\Knowledge\KnowledgeBase;
use LittyWatch\Market\CatalogFirstResolver;
use LittyWatch\Market\ContextualOfferListResolver;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
Schema::install($db);$kb=new KnowledgeBase($db);
$seed=[
 ['mini-celestial-sheep','Miniature Celestial Sheep','miniature',['celestial sheep']],
 ['mini-celestial-rat','Miniature Celestial Rat','miniature',['celestial rat']],
 ['vamp-bow','Vampiric Bow String','weapon-upgrade',['vamp bow','vampiric bow']],
 ['zealous-bow','Zealous Bow String','weapon-upgrade',['zealous bow']],
 ['powerstone','Powerstone of Courage','consumable',['powerstone','powerstones']],
 ['stygian','Stygian Gemstone','material',['stygian gemstones']],
 ['iron','Iron Ingot','material',['iron']],
 ['dust','Pile of Glittering Dust','material',['dust']],
];
foreach($seed as[$key,$name,$cat,$aliases]){$kb->upsertItem($key,$name,$cat,'test');foreach($aliases as$a)$kb->addAlias($key,$a,'test');}

$fail=[];$splitter=new ContextualOfferListResolver();
$cases=[
 ['Uded Celestial Sheep and Rat','Uded Celestial Sheep and Rat',['Miniature Celestial Sheep unded','Miniature Celestial Rat unded']],
 ['Weapon Mods','WTS Zealous, Vamp Bow',['Zealous Bow','Vamp Bow']],
 ['Powerstones , Stygian Gemstones','Powerstones , Stygian Gemstones',['Powerstones','Stygian Gemstones']],
 ['iron and dust','iron and dust',['iron','dust']],
];
foreach($cases as[$item,$ctx,$expect]){$got=$splitter->candidates($item,$ctx);if($got!==$expect)$fail[]=['split'=>$ctx,'got'=>$got,'expect'=>$expect];}

$resolver=new CatalogFirstResolver($db);
$row=['item'=>'Uded Celestial Sheep and Rat','item_key'=>'','market_key'=>'','raw_segment'=>'Uded Celestial Sheep and Rat','quality_status'=>'accepted'];
$r=$resolver->resolve($row,'WTS Uded Celestial Sheep and Rat');
$names=array_column($r,'item');sort($names);$exp=['Miniature Celestial Rat','Miniature Celestial Sheep'];sort($exp);
if($names!==$exp)$fail[]=['mini_list'=>$r,'expect'=>$exp];

$row=['item'=>'Weapon Mods','item_key'=>'','market_key'=>'','raw_segment'=>'WTS Zealous, Vamp Bow','quality_status'=>'accepted'];
$r=$resolver->resolve($row,'WTS Zealous, Vamp Bow');
$names=array_column($r,'item');sort($names);$exp=['Vampiric Bow String','Zealous Bow String'];sort($exp);
if($names!==$exp)$fail[]=['upgrade_list'=>$r,'expect'=>$exp];

// Safety: slash stats must not be interpreted as item lists.
if($splitter->candidates('11-22 20/20 Pre nerf Staffs','11-22 20/20 Pre nerf Staffs')!==[])$fail[]=['unsafe_stat_split'=>true];

if($fail){fwrite(STDERR,json_encode($fail,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);exit(1);} 
echo "Phase 3X contextual offer segmentation: OK\n";
