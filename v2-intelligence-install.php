<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Intelligence/Schema.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Intelligence\Schema;

header('Content-Type: application/json; charset=utf-8');
try {
    $pdo = Database::connect(__DIR__);
    Schema::ensure($pdo);
    echo json_encode(['ok' => true, 'version' => '2.2', 'message' => 'Market intelligence tabel is klaar.', 'next' => '/v2-intelligence-refresh.php'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
