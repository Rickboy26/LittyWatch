<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$parser = new ParserEngine(new Catalog(dirname(__DIR__).'/app/Data'));
$one = static function(string $message, string $item) use ($parser) {
    foreach ($parser->parse($message) as $offer) if ($offer->item === $item) return $offer;
    fwrite(STDERR, "Missing {$item} for {$message}\n"); exit(1);
};
$assert = static function($offer, float $unit, string $basis, string $label): void {
    if ($offer->price->unitEcto === null || abs($offer->price->unitEcto-$unit) > 0.00001) {
        fwrite(STDERR, "$label unit mismatch: ".var_export($offer->price->unitEcto,true)."\n"); exit(1);
    }
    if ($offer->price->basis !== $basis) {
        fwrite(STDERR, "$label basis mismatch: {$offer->price->basis}\n"); exit(1);
    }
};

// Explicit stack syntax stays explicit.
$o=$one('WTS Candy Corn 15e/stk','Candy Corn');
$assert($o,15.0/250.0,'stack','Candy Corn 15e/stk');

// Kamadan convention for this catalog-declared commodity: bare money is per stack.
$o=$one('WTS Candy Corn 20e','Candy Corn');
$assert($o,20.0/250.0,'stack_inferred','Candy Corn 20e');
$o=$one('WTB Candy Corn 1a','Candy Corn');
$assert($o,27.0/250.0,'stack_inferred','Candy Corn 1a');

// Regression guard: generic consumables must NOT all become stacks.
$o=$one('WTS Conset 8e','Conset');
if ($o->price->basis === 'stack_inferred' || $o->price->unitEcto !== null) {
    fwrite(STDERR,"Conset bare price incorrectly inferred as stack\n"); exit(1);
}

echo "Phase 3G stack-default semantics OK\n";
