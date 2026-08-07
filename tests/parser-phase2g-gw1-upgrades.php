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
 ['wts firebrand',['Firebrand']],
 ['drake flesh',['Chunk of Drake Flesh']],
 ['WTS Blessings of War',['Blessing of War']],
 ['wtb large equipment pack',['Large Equipment Pack']],
 ['WTS Ministerial Commendations 20e/stack',['Ministerial Commendation']],
 ['WTB GZC 6e/ea',['Gold Zaishen Coin']],
 ['WTB Stack of Silver zcoins 7a',['Silver Zaishen Coin']],
 ['WTB Charr Bags',['Charr Bag']],
 ['WTS 125 Hero StrongBoxes 1e/ea',['Hero\'s Strongbox']],
 ['WTS RED ROCKS 225-675e',['Red Rock Candy']],
 ['WTS DSR 6a WTB Rainbows 65a',['Rainbow Candy Cane']],
 ['wts m4m 500g',['Measure for Measure']],
 ['WTS Live for Today, Strength and Honor & Shield Handle of Fortitude',
    ['Live for Today','Strength and Honor','Shield Handle of Fortitude']],
 ['wts dont think twice 2e',['Don\'t Think Twice']],
 ['WTS Dance with Death (+15%/Stance)',['Dance with Death']],
 ['WTS Sheltered by Faith (Tips)',['Sheltered by Faith']],
 ['WTS AnA 5e',['Aptitude not Attitude']],
 ['WTS DSR 6A- Unded Dhuum - Madr',['Miniature Madruk Dhuum']],
 ['WTB Mini Forest Griffon & Wailing Lord 15a/ea',['Miniature Forest Griffon','Miniature Wailing Lord']],
 ['WTS q9 insc 2e/ea: IgneousBlade, GoldenMachete, CrestedMachette, PlatinumBlade',
    ['Igneous Blade','Golden Machete','Crested Machete','Platinum Blade']],
];

foreach($cases as [$msg,$expected]){
    $offers=$e->parse($msg);
    $names=array_values(array_unique(array_map(fn($o)=>$o->item,$offers)));
    foreach($expected as $name){
        if(!in_array($name,$names,true)) $failed[]=['message'=>$msg,'missing'=>$name,'got'=>$names];
    }
}

foreach([
 'WTB Mods Soul Reaping +5',
 'WTS 19% mods 10k ea',
 'WTB Tormented Weapons',
 'WTB 1 point alcohol stacks',
] as $msg){
    $offers=$e->parse($msg);
    if($offers!==[]) $failed[]=['generic_should_not_create_offer'=>$msg,'got'=>array_map(fn($o)=>$o->toArray(),$offers)];
}

echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
