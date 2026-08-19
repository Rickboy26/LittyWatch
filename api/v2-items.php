<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');


use LittyWatch\Infrastructure\Database;
use LittyWatch\Encyclopedia\ItemEncyclopediaService;

try {
    $root = dirname(__DIR__);
    $pdo = Database::connect($root);
    $service = new ItemEncyclopediaService($pdo, $root);

    $key = trim((string)($_GET['key'] ?? ''));
    if ($key !== '') {
        $item = $service->item($key);
        if ($item === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Item niet gevonden.']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'item' => $item,
            'markets' => $service->markets($key, 100),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'summary' => $service->summary(),
        'items' => $service->items((string)($_GET['q'] ?? ''), min(1000, max(1, (int)($_GET['limit'] ?? 250)))),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
