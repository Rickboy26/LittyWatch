<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__).'/app');
if(!extension_loaded('pdo_sqlite')){echo "Phase 3Z context-aware candidate pipeline: SKIP (pdo_sqlite missing)\n";exit(0);}

use LittyWatch\Knowledge\Schema;
use LittyWatch\Knowledge\KnowledgeBase;
use LittyWatch\Market\ContextAwareCandidatePipeline;
use LittyWatch\Market\CatalogFirstResolver;
use LittyWatch\Market\NoiseFragmentGate;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
Schema::install($db);$kb=new KnowledgeBase($db);
$seed=[
 ['mini-ghostly-hero','Miniature Ghostly Hero','miniature',['ghostly hero']],
 ['mini-zhed','Miniature Zhed','miniature',['zhed']],
 ['mini-livia','Miniature Livia','miniature',['livia']],
 ['mini-celestial-sheep','Miniature Celestial Sheep','miniature',['celestial sheep']],
 ['mini-celestial-rat','Miniature Celestial Rat','miniature',['celestial rat']],
 ['iron-ingot','Iron Ingot','material',['iron']],
 ['glittering-dust','Pile of Glittering Dust','material',['dust']],
];
foreach($seed as[$key,$name,$cat,$aliases]){$kb->upsertItem($key,$name,$cat,'test');foreach($aliases as$a)$kb->addAlias($key,$a,'test');}

$fail=[];$pipeline=new ContextAwareCandidatePipeline($db);
$row=['item'=>'Minis','item_key'=>'','raw_segment'=>'WTB unded Ghostly Hero / Zhed / Livia'];
$c=$pipeline->expand($row,$row['raw_segment']);
$got=array_column($c,'item');
$expect=['Miniature Ghostly Hero unded','Miniature Zhed unded','Miniature Livia unded'];
if($got!==$expect)$fail[]=['mini_candidates'=>$got,'expect'=>$expect];

$row=['item'=>'Uded Celestial Sheep and Rat','item_key'=>'','raw_segment'=>'Uded Celestial Sheep and Rat'];
$c=$pipeline->expand($row,$row['raw_segment']);$got=array_column($c,'item');
$expect=['Miniature Celestial Sheep unded','Miniature Celestial Rat unded'];
if($got!==$expect)$fail[]=['celestial_candidates'=>$got,'expect'=>$expect];

$row=['item'=>'iron and dust','item_key'=>'','raw_segment'=>'WTB iron and dust'];
$c=$pipeline->expand($row,$row['raw_segment']);$got=array_column($c,'item');
if($got!==['iron','dust'])$fail[]=['material_candidates'=>$got];

$resolver=new CatalogFirstResolver($db);
$row=['item'=>'iron and dust','item_key'=>'','market_key'=>'','raw_segment'=>'WTB iron and dust','quality_status'=>'review','quality_reason'=>'no_catalog_item','confidence'=>0.35];
$r=$resolver->resolve($row,'WTB iron and dust');
$resolved=[];foreach($r as$x)$resolved[$x['item']]=[$x['quality_status']??null,$x['quality_reason']??null];
if(($resolved['Iron Ingot']??null)!==['accepted','catalog_match']||($resolved['Pile of Glittering Dust']??null)!==['accepted','catalog_match'])$fail[]=['promotion'=>$resolved];

$noise=new NoiseFragmentGate();
foreach(['ran','nec','alc','sta','few mods','for 100k','270e (x4)','(x4)'] as$value){
 $i=$noise->inspect($value,$value);
 if(!$i['drop'])$fail[]=['noise_not_dropped'=>$value,'inspect'=>$i];
}

if($fail){fwrite(STDERR,json_encode($fail,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL);exit(1);}
echo "Phase 3Z context-aware candidate pipeline: OK\n";
