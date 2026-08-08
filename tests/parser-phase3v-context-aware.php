<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$catalog = new Catalog(dirname(__DIR__).'/app/Data');
$parser = new ParserEngine($catalog);
$failed=[];

$mustExclude=[
    'WTB outpost run to kodash bazaa',
    'WTS services: Outpost runs (tour)',
    'WTS Franks Crystal Desert Tours! Starts in Lions Arch! PM ME!',
    'Ferry to docks for tips 1/4',
];
foreach($mustExclude as $m){
    $o=$parser->parse($m);
    if($o!==[])$failed[]=['should_exclude'=>$m,'got'=>array_map(fn($x)=>$x->toArray(),$o)];
}

$neg=$parser->parse('wts unid golds (no scythes, shields or spears) 1k each');
$names=array_values(array_unique(array_map(fn($o)=>$o->item,$neg)));
if($names!==['Unidentified Gold'])$failed[]=['negation'=>$names];

$ctx=$parser->parse('WTS BDS q9 FC 35a | q11 Inspa 12a');
$ctxRows=array_map(fn($o)=>$o->toArray(),$ctx);
if(count($ctxRows)<2 || ($ctxRows[0]['item']??'')!=='Bone Dragon Staff' || ($ctxRows[1]['item']??'')!=='Bone Dragon Staff'){
    $failed[]=['inherit_bds'=>$ctxRows];
}

$variants=$parser->parse('WTS Eternal Shields: Q9 comm 70e, Q9 motivation 40e, Q10 tact 65e, Q10 comm 40e');
$vRows=array_map(fn($o)=>$o->toArray(),$variants);
if(count($vRows)!==4 || count(array_filter($vRows,fn($r)=>($r['item']??'')==='Eternal Shield'))!==4){
    $failed[]=['variant_list'=>$vRows];
}

$bundle=$parser->parse('WTS ObsiEdge / EternalBlade / VoltaicSpear (all unidentified) in the package 22a');
$bRows=array_map(fn($o)=>$o->toArray(),$bundle);
$bNames=array_values(array_unique(array_map(fn($r)=>$r['item']??'', $bRows)));
sort($bNames);
$want=['Eternal Blade','Obsidian Edge','Voltaic Spear']; sort($want);
if($bNames!==$want || array_filter($bNames,fn($n)=>str_starts_with($n,'Bundle:'))){
    $failed[]=['bundle'=>$bRows];
}

$mini=$parser->parse('WTB ded ghostly Hero 250a');
if(($mini[0]->item??'')!=='Miniature Ghostly Hero')$failed[]=['ghostly'=>array_map(fn($o)=>$o->toArray(),$mini)];

$rurik=$parser->parse('WTS Mini Undead Prince 150a');
if(($rurik[0]->item??'')!=='Miniature Undead Prince Rurik')$failed[]=['rurik'=>array_map(fn($o)=>$o->toArray(),$rurik)];

$insc=$parser->parse('WTS Run for Your Life (-2/Stance)');
if($insc===[])$failed[]=['run_for_your_life'=>'incorrectly excluded as service'];

echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
