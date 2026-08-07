<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$catalog=new Catalog(dirname(__DIR__).'/app/Data');
$parser=new ParserEngine($catalog);

$expect=function(string $message,string $want)use($parser):void{
    $offers=$parser->parse($message);
    foreach($offers as $offer){
        if (mb_strtolower($offer->item)===mb_strtolower($want)) return;
    }
    fwrite(STDERR,"FAIL $message => expected $want, got ".implode(', ',array_map(fn($o)=>$o->item,$offers))."\n");
    exit(1);
};

$expect('WTS Elite Toms 2e','Elite Tome');
$expect('WTS nec tomes','Necromancer Tome');
$expect('WTS Stack Strategist box 36a',"Strategist's Zaishen Strongbox");
$expect('WTS 100 Balthazar flames 80e','Flames of Balthazar');
$expect('WTS Vials Absinthe 2e/stk','Vial of Absinthe');
$expect('WTB jade wind orbs','Jade Wind Orb');
$expect('WTS Cons 10a','Conset');
$expect('WTS Q10 VS 10a','Voltaic Spear');

// Safety: barter context must not become Conset.
foreach($parser->parse('WTB Tengu Support Flare Guard Cons 1:1') as $offer){
    if (mb_strtolower($offer->item)==='conset'){
        fwrite(STDERR,"FAIL barter Cons hijacked as Conset\n"); exit(1);
    }
}
echo "Phase 3L.14 alias + commodity normalization OK\n";
