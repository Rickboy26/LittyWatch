<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');


use LittyWatch\Alerts\AlertService;
use LittyWatch\Infrastructure\Database;

try {
    $pdo = Database::connect(dirname(__DIR__));
    $service = new AlertService($pdo);
    $service->install();

    echo json_encode([
        'ok' => true,
        'evaluation' => isset($_GET['evaluate']) ? $service->evaluate() : null,
        'alerts' => $service->all(),
        'events' => $service->events(min(500, max(1, (int)($_GET['limit'] ?? 100)))),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
