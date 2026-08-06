<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Schema.php';
require __DIR__ . '/app/V2/Alerts/AlertService.php';

use LittyWatch\V2\Alerts\AlertService;
use LittyWatch\V2\Database;
use LittyWatch\V2\Schema;

try {
    $pdo = Database::connect(__DIR__);
    Schema::ensure($pdo);
    (new AlertService($pdo))->install();
    echo json_encode(['ok'=>true,'version'=>'2.8-watchlist-alerts','message'=>'V2.8 tabellen en kolommen zijn gereed.','next'=>'/v2-watchlist.php'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
}
