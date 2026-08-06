<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/V2/Database.php';
require $root . '/app/V2/Intelligence/Schema.php';
require $root . '/app/V2/Intelligence/MarketIntelligenceService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Intelligence\MarketIntelligenceService;

try {
    $result = (new MarketIntelligenceService(Database::connect($root)))->rebuild();
    echo '[' . date('c') . '] rebuilt=' . $result['markets'] . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('c') . '] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
