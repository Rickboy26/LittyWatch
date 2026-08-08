<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__).'/app');
if(!extension_loaded('pdo_sqlite')){echo "Phase 3Y noise/mini/consumable recovery: SKIP (pdo_sqlite missing)\n";exit(0);}

use LittyWatch\Knowledge\Schema;
use LittyWatch\Knowledge\KnowledgeBase;
use LittyWatch\Market\CatalogFirstResolver;
use LittyWatch\Market\ControlledCatalogResolver;
use LittyWatch\Market\NoiseFragmentGate;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
Schema::install($db);$kb=new KnowledgeBase($db);
$seed=[
 ['mini-water-djinn','Miniature Water Djinn','miniature',['water djinn']],
 ['mini-xun-rao','Miniature Preacher Xun Rao','miniature',['preacher xun rao']],
 ['mini-asura','Miniature Asura','miniature',['asura']],
 ['ghozers-key',"Ghozer's Key",'key',["ghozer's key"]],
 ['gold-zaishen-coin','Gold Zaishen Coin','currency',['gold zaishen coin']],
];
foreach($seed as[$key,$name,$cat,$aliases]){$kb->upsertItem($key,$name,$cat,'test');foreach($aliases as$a)$kb->addAlias($key,$a,'test');}

$fail=[];
$noise=new NoiseFragmentGate();
foreach(['left','(x6)','for','elite','Normal','arm','for full collection','OS, trade to see'] as$value){
 $r=$noise->inspect($value,$value);if(!$r['drop'])$fail[]=['noise_not_dropped'=>$value,'result'=>$r];
}
foreach(["Ghozer's Key",'Miniature Asura','Gold Zaishen Coin'] as$value){
 $r=$noise->inspect($value,$value);if($r['drop'])$fail[]=['real_item_dropped'=>$value,'result'=>$r];
}

$catalog=new CatalogFirstResolver($db);
$miniCases=[
 ["Mini's Water Djinn",'WTS unded Mini\'s Water Djinn','Miniature Water Djinn','unded'],
 ['Preacher Xun Rao mini','WTS ded Preacher Xun Rao mini','Miniature Preacher Xun Rao','ded'],
 ['mini unded Asura','WTS mini unded Asura','Miniature Asura','unded'],
];
foreach($miniCases as[$item,$message,$expect,$state]){
 $row=['item'=>$item,'item_key'=>'','market_key'=>'','raw_segment'=>$message,'quality_status'=>'accepted'];
 $r=$catalog->resolve($row,$message);
 if(($r[0]['item']??null)!==$expect||($r[0]['variant']??null)!==$state)$fail[]=['mini'=>$item,'got'=>$r,'expect'=>[$expect,$state]];
}

$controlled=new ControlledCatalogResolver($db);
$r=$controlled->resolve("Ghozer´s Key / 15a",'',"WTS Ghozer´s Key / 15a");
if(($r['name']??null)!=="Ghozer's Key")$fail[]=['ghozer'=>$r];
$r=$controlled->resolve('Gold zc','','WTS Gold zc 5e');
if(($r['name']??null)!=='Gold Zaishen Coin')$fail[]=['gold_zc'=>$r];

if($fail){fwrite(STDERR,json_encode($fail,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL);exit(1);}
echo "Phase 3Y noise/mini/consumable recovery: OK\n";
