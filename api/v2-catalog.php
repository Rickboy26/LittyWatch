<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require dirname(__DIR__) . '/app/V2/Database.php';
require dirname(__DIR__) . '/app/V2/Encyclopedia/WikiClient.php';
require dirname(__DIR__) . '/app/V2/Encyclopedia/CatalogImportService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Encyclopedia\CatalogImportService;
use LittyWatch\V2\Encyclopedia\WikiClient;

try {
    $root = dirname(__DIR__);
    $pdo = Database::connect($root);
    $service = new CatalogImportService($pdo, new WikiClient());
    $service->install();

    echo json_encode([
        'ok' => true,
        'summary' => $service->summary(),
        'categories' => $service->categories(min(1000, max(1, (int)($_GET['category_limit'] ?? 250)))),
        'items' => $service->items(
            (string)($_GET['q'] ?? ''),
            min(3000, max(1, (int)($_GET['limit'] ?? 500)))
        ),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
