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
$e=new \LittyWatch\Parser\ParserEngine($c); $f=[];

$cases=[
 ['WTS VS q9 30a, Turtle Stones 35a','Jadeite Summoning Stone'],
 ['WTS Cons 10A/stack x14 - Golden Eggs 10E','Golden Egg'],
 ['WTS Conset 2e~Hero Box 2e~Diessa 7a~Rin 25e~Tengu 2e~Ghastly Stone 40a',"Hero's Strongbox"],
 ['WTS Conset 2e~Hero Box 2e~Diessa 7a~Rin 25e~Tengu 2e~Ghastly Stone 40a','Diessa Chalice'],
 ['WTS Conset 2e~Hero Box 2e~Diessa 7a~Rin 25e~Tengu 2e~Ghastly Stone 40a','Rin Relic'],
 ['WTB 5 stack char carving 2 ambr','Charr Carving'],
 ['WTS DSR 6A','Dhuum\'s Soul Reaper'],
];
foreach($cases as [$m,$want]){
  $o=$e->parse($m); $names=array_map(fn($x)=>$x->item,$o);
  if(!in_array($want,$names,true)) $f[]=['missing',$m,$want,$names];
  foreach($o as $x) if($x->item===$want && $x->status!=='accepted') $f[]=['not_accepted',$m,$x->toArray()];
}

foreach([
 'WTT or Sale -- Cons GoM for EoC/AoS ---',
 'WTB: dervish, necro, mesmer, ritualist elite tome 2e each',
 'wtb lockpicks 21e ea ( 5 stacks',
 'Buying :: Stacks of zKeys :: 13a/stack, 2 stacks = 27a :: Trade/PM.',
 'WTS 10 GotT // 25e or 1a',
] as $m){
  foreach($e->parse($m) as $x){
    $bad=mb_strtolower($x->item);
    if(in_array($bad,['mesmer','necro','stacks','stacks)','or 1a','each open tra','or sale -- cons gom for eoc/aos','cons gom for eoc/aos'],true)
       || str_contains($bad,'stacks = :: trade')) $f[]=['orphan_context',$m,$x->toArray()];
  }
}

$tax=new \LittyWatch\Parser\ItemTaxonomy($c->taxonomy());
foreach(['mesmer','necro','stacks','trade','ded'] as $v){
 if($tax->classifyNonItemContext($v)===null) $f[]=['taxonomy_missed',$v];
}
if(!$tax->isGenericName('Miniature') || !$tax->isGenericName('Staff')) $f[]=['generic_names'];

echo json_encode(['ok'=>$f===[],'failed'=>$f],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($f===[]?0:1);
