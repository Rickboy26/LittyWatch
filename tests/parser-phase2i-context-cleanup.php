<?php
declare(strict_types=1);
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $v, ?string $e=null): string { return strtolower($v); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $v, ?string $e=null): string { return strtoupper($v); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $v, ?string $e=null): int { return strlen($v); } }
if (!function_exists('mb_stripos')) { function mb_stripos(string $h,string $n,int $o=0,?string $e=null): int|false { return stripos($h,$n,$o); } }
if (!function_exists('mb_substr')) { function mb_substr(string $v,int $s,?int $l=null,?string $e=null): string { return $l===null?substr($v,$s):substr($v,$s,$l); } }
require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');
$c=new \LittyWatch\Parser\Catalog(dirname(__DIR__) . '/app/Data'); $e=new \LittyWatch\Parser\ParserEngine($c); $failed=[];
$cases=[['WTB 350 Golden Zaishen Coins 1750e','Gold Zaishen Coin'],['WTB Flames of Balthazar 1e/ea 110x','Flames of Balthazar'],['WTS Inscribed Chakram q9 (Dom) Gold + Insc 3e/ea','Inscribed Chakram'],['wts sup rune vigor +50 hp 20k','Superior Rune of Vigor'],['WTS 35 Stygian Gems for 17e','Stygian Gem'],['Wts grog 6e x10','Grog'],['WTB BONE IDOL DEATH MAGIC (HORNED SKULL)','Bone Idol'],['wts stack of iron 6k','Iron Ingot']];
foreach($cases as [$msg,$want]){$o=$e->parse($msg);$names=array_map(fn($x)=>$x->item,$o);if(!in_array($want,$names,true))$failed[]=['message'=>$msg,'want'=>$want,'got'=>$names];}
foreach(['WTS Insightful Staff Head, Bow/Axe/Spear Grip of Defense','WTS Wand Wrapping of the Mesmer','WTS Zealous Axe Haft','WTB all celestial minis'] as $msg){foreach($e->parse($msg) as $x)if(in_array($x->item,['Staff','Wand','Axe','Miniature','Blessing of War'],true))$failed[]=['false_generic_match'=>$msg,'got'=>$x->item];}
foreach(['WTS bow mods: zealous, enchant20%, +30HP, Armor+5 3k ea','WTB Q8 Bows / Q7 Bows'] as $msg){foreach($e->parse($msg) as $x)if($x->item==='Blessing of War')$failed[]=['false_bow_blessing'=>$msg];}
foreach(['wtb Gaki Polymock piece'] as $msg){foreach($e->parse($msg) as $x)if($x->item==='Mystical Summoning Stone (Gaki)')$failed[]=['false_gaki_summon'=>$msg];}
$o=$e->parse('WTB q12/13 death BDS'); if($o===[]||$o[0]->item!=='Bone Dragon Staff'||$o[0]->status!=='accepted')$failed[]=['bds_should_accept','got'=>array_map(fn($x)=>$x->toArray(),$o)];
echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL; exit($failed===[]?0:1);
