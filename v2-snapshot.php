<?php

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Schema.php';
require __DIR__ . '/app/V2/MarketStats.php';
require __DIR__ . '/app/V2/SnapshotService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Schema;
use LittyWatch\V2\MarketStats;
use LittyWatch\V2\SnapshotService;

try {
    $pdo = Database::connect(__DIR__);
    Schema::ensure($pdo);
    $service = new SnapshotService($pdo, new MarketStats($pdo));
    $count = $service->captureAll(250);
    echo json_encode(['ok' => true, 'version' => '2.1', 'snapshots_created' => $count, 'captured_at' => date(DATE_ATOM)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
