<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__).'/app');
if(!extension_loaded('pdo_sqlite')){echo "Phase 3W context catalog intelligence: SKIP (pdo_sqlite missing)\n";exit(0);}

use LittyWatch\Knowledge\Schema;
use LittyWatch\Knowledge\KnowledgeBase;
use LittyWatch\Market\CatalogFirstResolver;
use LittyWatch\Market\ControlledCatalogResolver;
use LittyWatch\Market\StrictCatalogGate;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
Schema::install($db);$kb=new KnowledgeBase($db);
$seed=[
 ['staff-wrapping-of-enchanting','Staff Wrapping of Enchanting','weapon-upgrade',['staff wrapping of enchanting','wrapping of enchanting']],
 ['vampiric-spearhead','Vampiric Spearhead','weapon-upgrade',['vampiric spearhead','vamp spear','vamp spearhead']],
 ['critical-spear-grip','Spear Grip of Critical Strikes','weapon-upgrade',['critical spear grip','crit spear','+5 crit spear']],
 ['soul-reaping-staff-wrapping','Staff Wrapping of Soul Reaping','weapon-upgrade',['+5 sr staff wrapping','sr staff wrap']],
 ['soul-reaping-wand-wrapping','Wand Wrapping of Soul Reaping','weapon-upgrade',['+5 sr wand wrapping','sr wand wrap']],
 ['miku-potion',"Miku's Potion",'consumable',["miku's potion",'mikus potion']],
 ['miniature-ghostly-hero','Miniature Ghostly Hero','miniature',['ghostly hero']],
 ['miniature-undead-prince-rurik','Miniature Undead Prince Rurik','miniature',['undead prince rurik']],
 ['generic-staff','Staff','weapon',[]],
];
foreach($seed as[$key,$name,$cat,$aliases]){$kb->upsertItem($key,$name,$cat,'test');foreach($aliases as$a)$kb->addAlias($key,$a,'test');}

$fail=[];$resolver=new ControlledCatalogResolver($db);
$cases=[
 ['Staff','staff','WTS Staff wrap enchanting 20% 5e/ea','Staff Wrapping of Enchanting'],
 ['Spear','spear','WTB Vamp Spear [x31]','Vampiric Spearhead'],
 ['Spear','spear','WTB +5 Crit Spear [x24]','Spear Grip of Critical Strikes'],
 ['Staff','staff','WTB +5 SR Staff Wrapping','Staff Wrapping of Soul Reaping'],
 ['Wand','wand','WTB +5 SR Wand Wrapping','Wand Wrapping of Soul Reaping'],
 ['Miku potion','','WTS Miku potion 3e','Miku\'s Potion'],
];
foreach($cases as[$item,$key,$ctx,$expect]){$r=$resolver->resolve($item,$key,$ctx);if(($r['name']??null)!==$expect)$fail[]=['case'=>$ctx,'got'=>$r,'expect'=>$expect];}

$gate=new StrictCatalogGate($db);
foreach(['Staff','Wand','Bow','Shield','Tonic','Focus item'] as$generic){$r=$gate->inspect($generic,'');if($r['allowed'])$fail[]=['generic_allowed'=>$generic,'result'=>$r];}

$catalog=new CatalogFirstResolver($db);
$miniRow=['item'=>'Ghostly Hero','item_key'=>'','raw_segment'=>'WTB unded Ghostly Hero','quality_status'=>'accepted'];
$r=$catalog->resolve($miniRow,'WTB unded Ghostly Hero');
if(($r[0]['item']??null)!=='Miniature Ghostly Hero'||($r[0]['variant']??null)!=='unded')$fail[]=['mini_ghostly'=>$r];
$rurikRow=['item'=>'Miniature Undead Prince Rurik','item_key'=>'','raw_segment'=>'WTS ded Miniature Undead Prince Rurik','quality_status'=>'accepted'];
$r=$catalog->resolve($rurikRow,'WTS ded Miniature Undead Prince Rurik');
if(($r[0]['item']??null)!=='Miniature Undead Prince Rurik'||($r[0]['variant']??null)!=='ded')$fail[]=['mini_rurik'=>$r];

if($fail){fwrite(STDERR,json_encode($fail,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);exit(1);} 
echo "Phase 3W context catalog intelligence: OK\n";
