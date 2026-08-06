<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Encyclopedia/ItemEncyclopediaService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Encyclopedia\ItemEncyclopediaService;

try {
    $pdo = Database::connect(__DIR__);
    $service = new ItemEncyclopediaService($pdo, __DIR__);
    $service->install();

    echo json_encode([
        'ok' => true,
        'version' => '2.7-item-encyclopedia',
        'message' => 'Encyclopedia-tabellen zijn aangemaakt of bestonden al.',
        'summary' => $service->summary(),
        'next' => '/v2-items.php',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
