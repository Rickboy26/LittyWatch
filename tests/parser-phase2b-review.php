<?php
declare(strict_types=1);
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $v, ?string $e=null): string { return strtolower($v); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $v, ?string $e=null): string { return strtoupper($v); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $v, ?string $e=null): int { return strlen($v); } }
if (!function_exists('mb_stripos')) { function mb_stripos(string $h,string $n,int $o=0,?string $e=null): int|false { return stripos($h,$n,$o); } }
if (!function_exists('mb_substr')) { function mb_substr(string $v,int $s,?int $l=null,?string $e=null): string { return $l===null?substr($v,$s):substr($v,$s,$l); } }
require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');
$c=new \LittyWatch\Parser\Catalog(dirname(__DIR__) . '/app/Data');
$e=new \LittyWatch\Parser\ParserEngine($c);
$failed=[];

$cases=[
 ['WTS unid Q9Fellblade','Fellblade','q9'],
 ['WTB 120 Obby Shards','Obsidian Shard',null],
 ['WTB Stack of res scrolls','Scroll of Resurrection',null],
 ['WTB Stack Cookies!!!','Pumpkin Cookie',null],
 ['WTB stack of pies','Slice of Pumpkin Pie',null],
 ['wts lunars = 18e','Lunar Fortune',null],
 ['wtb droknar key 15e/ea',"Droknar's Key",null],
 ['WTS Lightbringer Scrolls','Scroll of the Lightbringer',null],
 ['wtb kabob','Drake Kabob',null],
];
foreach($cases as [$msg,$name,$req]){
 $offers=$e->parse($msg); $ok=false;
 foreach($offers as $o){ if($o->item===$name && ($req===null || ($o->modifiers['requirement']??null)===$req)){$ok=true;break;} }
 if(!$ok)$failed[]=['message'=>$msg,'expected'=>$name,'offers'=>array_map(fn($o)=>$o->toArray(),$offers)];
}

$tomes=$e->parse('WTS normal tomes assa, warr 500 each');
$tn=array_map(fn($o)=>$o->item,$tomes);
foreach(['Assassin Tome','Warrior Tome'] as $n){ if(!in_array($n,$tn,true))$failed[]=['tomes'=>$tn,'missing'=>$n]; }
$tomes2=$e->parse('WTS tomes P Mo N R A');
$tn2=array_map(fn($o)=>$o->item,$tomes2);
foreach(['Paragon Tome','Monk Tome','Necromancer Tome','Ranger Tome','Assassin Tome'] as $n){ if(!in_array($n,$tn2,true))$failed[]=['tomes2'=>$tn2,'missing'=>$n]; }

$et=$e->parse('WTS Q11 Eternal Blade | Insc');
$found=false; foreach($et as $o){ if($o->item==='Eternal Blade' && ($o->modifiers['requirement']??null)==='q11' && ($o->modifiers['inscribable']??false)===true)$found=true; }
if(!$found)$failed[]=['eternal'=>array_map(fn($o)=>$o->toArray(),$et)];

echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
