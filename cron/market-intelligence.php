<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$root = dirname(__DIR__);

use LittyWatch\Infrastructure\Database;
use LittyWatch\Intelligence\MarketIntelligenceService;

try {
    $result = (new MarketIntelligenceService(Database::connect($root)))->rebuild();
    echo '[' . date('c') . '] rebuilt=' . $result['markets'] . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('c') . '] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
