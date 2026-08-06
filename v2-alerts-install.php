<?php

declare(strict_types=1);

use LittyWatch\V2\Alerts\AlertService;
use LittyWatch\V2\Database;
use LittyWatch\V2\Schema;

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/app/V2/bootstrap-db.php';

    $pdo = Database::connect(__DIR__);
    Schema::ensure($pdo);

    $alerts = new AlertService($pdo);
    $alerts->install();

    echo json_encode([
        'ok' => true,
        'version' => '2.9.1-cron-status',
        'message' => 'Watchlist- en alerttabellen zijn gecontroleerd en bijgewerkt. Configureer daarna de alert-cronjob.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
