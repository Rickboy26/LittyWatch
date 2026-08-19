<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');


use LittyWatch\Infrastructure\Database;
use LittyWatch\Search\GlobalSearchService;

try {
    $root = dirname(__DIR__);
    $pdo = Database::connect($root);
    $service = new GlobalSearchService($pdo);
    $query = trim((string)($_GET['q'] ?? ''));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 12)));

    echo json_encode([
        'ok' => true,
        'query' => $query,
        'summary' => $service->summary(),
        'results' => $service->search($query, $limit),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
