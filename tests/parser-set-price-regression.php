<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use LittyWatch\Parser\ParsedPrice;

$price = new ParsedPrice(50.0, 'e', 50.0, 'total', null, 50.0, '50e');
$setQuantity = 5.0;
$setPrice = new ParsedPrice(
    $price->amount,
    $price->currency,
    $price->ectoValue,
    'set',
    $setQuantity,
    $price->ectoValue !== null ? $price->ectoValue / $setQuantity : null,
    $price->raw,
);

if ($setPrice->ectoValue !== 50.0) {
    throw new RuntimeException('ectoValue was not preserved for set price');
}
if ($setPrice->unitEcto !== 10.0) {
    throw new RuntimeException('unitEcto was not calculated correctly for set price');
}

echo "parser-set-price-regression: OK\n";
