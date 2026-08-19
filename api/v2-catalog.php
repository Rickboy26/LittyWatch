<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');


use LittyWatch\Infrastructure\Database;
use LittyWatch\Encyclopedia\CatalogImportService;
use LittyWatch\Encyclopedia\WikiClient;

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
