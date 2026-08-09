<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__).'/app');
if(!extension_loaded('pdo_sqlite')){echo "Phase 4B clause reconstruction + weapon skin: SKIP (pdo_sqlite missing)\n";exit(0);}

use LittyWatch\Knowledge\Schema;
use LittyWatch\Knowledge\KnowledgeBase;
use LittyWatch\Market\CatalogFirstResolver;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
Schema::install($db);$kb=new KnowledgeBase($db);
$seed=[
 ['eternal-shield','Eternal Shield','shield',['eternal shield']],
 ['crystalline-sword','Crystalline Sword','sword',['crystalline sword']],
 ['flame-artifact','Flame Artifact','focus',['flame artifact']],
 ['amethyst-aegis','Amethyst Aegis','shield',['amethyst aegis']],
 ['eaglecrest-axe','Eaglecrest Axe','axe',['eaglecrest axe']],
 ['zaishen-key','Zaishen Key','key',['zkey','zkeys']],
];
foreach($seed as[$key,$name,$cat,$aliases]){$kb->upsertItem($key,$name,$cat,'test');foreach($aliases as$a)$kb->addAlias($key,$a,'test');}

$r=new CatalogFirstResolver($db);$fail=[];
$base=['trade_type'=>'sell','item_key'=>'','market_key'=>'','normalized_market_key'=>'','requirement'=>null,'attribute_key'=>null,'attribute_name'=>null,'is_oldschool'=>0,'is_inscribable'=>0,'mods_json'=>'{}','relevant_json'=>'{}','profile_json'=>'{}','quantity'=>1,'price_amount'=>null,'price_currency'=>null,'price_ecto'=>null,'unit_price_ecto'=>null,'price_basis'=>null,'confidence'=>0.60,'quality_status'=>'review','quality_reason'=>'catalog_first_unresolved','exchange_item'=>null,'exchange_item_key'=>null,'exchange_give_quantity'=>null,'exchange_receive_quantity'=>null];
$cases=[
 ['Shield','WTB Eshield q9 Com 15e','Eternal Shield',9,'Command',0,0],
 ['Sword','WTS crystalline q11 +14 with enchant','Crystalline Sword',11,null,0,0],
 ['Focus','WTS Artifact flame q8 +12energy (blue)','Flame Artifact',8,null,0,0],
 ['Shield','WTS Amethys Aegis Q11-13','Amethyst Aegis',11,null,0,0],
 ['Axe','WTS Eaglecrest r10','Eaglecrest Axe',10,null,0,0],
 ['Sword','WTS crystalline OS q11 +14 with enchant','Crystalline Sword',11,null,1,0],
];
foreach($cases as[$item,$seg,$expect,$req,$attr,$os,$insc]){
 $row=$base+['item'=>$item,'raw_segment'=>$seg];$got=$r->resolve($row,$seg);
 if(count($got)!==1){$fail[]=['case'=>$seg,'got'=>$got,'reason'=>'count'];continue;}
 $g=$got[0];
 if(($g['item']??null)!==$expect||($g['requirement']??null)!==$req||($g['attribute_name']??null)!==$attr||(int)($g['is_oldschool']??0)!==$os||(int)($g['is_inscribable']??0)!==$insc)$fail[]=['case'=>$seg,'got'=>$g,'expect'=>[$expect,$req,$attr,$os,$insc]];
}
// Generic family without a concrete skin must remain unresolved.
$row=$base+['item'=>'Axe','raw_segment'=>'WTS Axe q9 5e'];
if($r->resolve($row,$row['raw_segment'])!==[])$fail[]=['generic_weapon_should_not_resolve'=>true];

if($fail){fwrite(STDERR,json_encode($fail,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL);exit(1);}echo "Phase 4B clause reconstruction + weapon skin: OK\n";
