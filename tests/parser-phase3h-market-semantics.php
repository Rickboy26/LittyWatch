<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\MarketSemantics;
use LittyWatch\Parser\ParserEngine;

$catalog = new Catalog(dirname(__DIR__).'/app/Data');
$parser = new ParserEngine($catalog);
$one = static function(string $message, string $item) use ($parser) {
    foreach ($parser->parse($message) as $offer) if ($offer->item === $item) return $offer;
    fwrite(STDERR, "Missing {$item}: {$message}\n"); exit(1);
};
$unit = static function($offer, float $expected, string $label): void {
    if ($offer->price->unitEcto === null || abs($offer->price->unitEcto-$expected) > 0.00001) {
        fwrite(STDERR, $label.' wrong '.json_encode($offer->price->toArray())."\n"); exit(1);
    }
};

// Catalog default quote basis applies when syntax is bare.
$o=$one('WTS Candy Corn 20e','Candy Corn');
$unit($o,20/250,'Candy Corn bare stack quote');
if ($o->price->basis !== 'stack_inferred' || $o->price->quantity !== 250.0) exit(1);

// Explicit syntax and catalog semantics converge on the same canonical unit.
$o=$one('WTS Candy Corn 15e/stk','Candy Corn');
$unit($o,15/250,'Candy Corn explicit stack');
if ($o->price->quantity !== 250.0) exit(1);

// Royal Gift now declares the same semantics in catalog metadata.
$o=$one('WTB Royal Gifts 9a','Royal Gift');
$unit($o,(9*27)/250,'Royal Gift bare stack quote');
if ($o->price->basis !== 'stack_inferred') exit(1);
$o=$one('WTS Royal Gift Stacks (x8) 8a','Royal Gift');
$unit($o,(8*27)/(8*250),'Royal Gift x8 stack total');
if ($o->price->quantity !== 2000.0) exit(1);

// Phase 3L.1: Conset bare ecto is per set; bare armbrace is stack.
$o=$one('WTS Conset 2e','Conset'); $unit($o,2.0,'Conset each quote');
$o=$one('WTS Conset 9a','Conset'); $unit($o,(9*27)/250,'Conset armbrace stack quote');

// Metadata parser supports non-default quote sizes without parser regex changes.
$m=MarketSemantics::fromItem(['market_quote_basis'=>'stack','market_quote_size'=>100,'market_display_basis'=>'each']);
if(!$m->isStackQuoted() || $m->quoteSize!==100.0 || $m->displayBasis!=='each') exit(1);

// Critical previous market regressions remain intact.
$o=$one('WTS armbraces 27e x6','Armbrace of Truth'); $unit($o,27.0,'Armbrace x6');

echo "Phase 3H canonical market semantics OK\n";
