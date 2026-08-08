<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__).'/app');
if(!extension_loaded('pdo_sqlite')){echo "Phase 4A known entity + weapon context: SKIP (pdo_sqlite missing)\n";exit(0);}

use LittyWatch\Knowledge\Schema;
use LittyWatch\Knowledge\KnowledgeBase;
use LittyWatch\Market\ControlledCatalogResolver;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
Schema::install($db);$kb=new KnowledgeBase($db);
$seed=[
 ['eternal-shield','Eternal Shield','shield',['eshield','eternal shield']],
 ['crystalline-sword','Crystalline Sword','sword',['crystalline','crystalline sword']],
 ['flame-artifact','Flame Artifact','focus',['artifact flame','flame artifact']],
 ['ghozers-key',"Ghozer's Key",'key',["ghozer's key",'ghozer key']],
 ['gold-zaishen-coin','Gold Zaishen Coin','reward-trophy',['gold z coin','gold z coins']],
 ['zaishen-summoning-stone','Zaishen Summoning Stone','consumable',['zaishen summoning stone']],
 ['powerstone-of-courage','Powerstone of Courage','consumable',['powerstone of courage']],
 ['stygian-gem','Stygian Gem','trophy',['stygian gem','stygian gems']],
 ['tanned-hide-square','Tanned Hide Square','material',['tanned hide square']],
 ['scale','Scale','material',['scale']],
 ['essence-of-celerity','Essence of Celerity','consumable',['essence of celerity']],
 ['shiros-tonic',"Shiro's Tonic",'tonic',["shiro's tonic"]],
 ['generic-axe','Axe','weapon',[]],
];
foreach($seed as[$key,$name,$cat,$aliases]){$kb->upsertItem($key,$name,$cat,'test');foreach($aliases as$a)$kb->addAlias($key,$a,'test');}

$r=new ControlledCatalogResolver($db);$fail=[];
$cases=[
 ['Shield','shield','WTB Eshield q9 Com','Eternal Shield'],
 ['Sword','sword','WTS crystalline q11 +14 with enchant','Crystalline Sword'],
 ['Focus','focus','WTS Artifact flame q8 +12energy (blue)','Flame Artifact'],
 ["Ghozer´s Key / 15a",'','WTS Ghozer´s Key / 15a',"Ghozer's Key"],
 ['Golden z coins','','WTB Golden z coins','Gold Zaishen Coin'],
 ['Zaishen Stones','','WTS Zaishen Stones','Zaishen Summoning Stone'],
 ['Powerstones','','WTS Powerstones','Powerstone of Courage'],
 ['Stygian Gemstones','','WTS Stygian Gemstones','Stygian Gem'],
 ['Tanned Hide','','WTS Tanned Hide','Tanned Hide Square'],
 ['Scales','','WTS Scales','Scale'],
 ['Celerity','','WTS Celerity','Essence of Celerity'],
 ['Shiro potion','','WTS Shiro potion',"Shiro's Tonic"],
];
foreach($cases as[$item,$key,$ctx,$expect]){$got=$r->resolve($item,$key,$ctx);if(($got['name']??null)!==$expect)$fail[]=['item'=>$item,'ctx'=>$ctx,'got'=>$got,'expect'=>$expect];}

// Safety rail: generic weapon + only requirement has no concrete skin evidence.
$got=$r->resolve('Axe','axe','WTS Axe q9 5e');
if($got!==null)$fail[]=['generic_weapon_must_stay_unresolved'=>$got];

// Safety rail: equal evidence for two shield aliases is ambiguous.
$kb->upsertItem('other-shield','Other Shield','shield','test');$kb->addAlias('other-shield','eshield','test');
$got=$r->resolve('Shield','shield','WTB Eshield q9 Com');
if($got!==null)$fail[]=['ambiguous_embedded_alias_must_not_resolve'=>$got];

if($fail){fwrite(STDERR,json_encode($fail,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL);exit(1);}
echo "Phase 4A known entity + weapon context: OK\n";
