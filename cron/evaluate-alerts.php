<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/V2/Database.php';
require dirname(__DIR__) . '/app/V2/Alerts/AlertService.php';
require dirname(__DIR__) . '/app/V2/RuntimeStatus.php';

use LittyWatch\V2\Alerts\AlertService;
use LittyWatch\V2\Database;
use LittyWatch\V2\RuntimeStatus;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Alleen via CLI/cron.\n");
}

try {
    $pdo = Database::connect(dirname(__DIR__));
    $result = (new AlertService($pdo))->evaluate();
    RuntimeStatus::write(dirname(__DIR__), 'alerts', ['ok'=>true,'message'=>'Alertcontrole voltooid'] + $result);
    echo json_encode(['ok'=>true,'evaluated_at'=>date(DATE_ATOM)] + $result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    RuntimeStatus::write(dirname(__DIR__), 'alerts', ['ok'=>false,'message'=>$e->getMessage()]);
    fwrite(STDERR, json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
