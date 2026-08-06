<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/V2/Database.php';
require dirname(__DIR__) . '/app/V2/Alerts/AlertService.php';

use LittyWatch\V2\Alerts\AlertService;
use LittyWatch\V2\Database;

try {
    $pdo = Database::connect(dirname(__DIR__));
    $result = (new AlertService($pdo))->evaluate();
    echo '[' . date(DATE_ATOM) . '] ' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date(DATE_ATOM) . '] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
