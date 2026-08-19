<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

use LittyWatch\Infrastructure\Database;
use LittyWatch\Intelligence\MarketExplorerService;

try {
    $service = new MarketExplorerService(Database::connect(dirname(__DIR__)));
    $key = trim((string)($_GET['key'] ?? ''));
    if ($key !== '') {
        $market = $service->detail($key);
        if ($market === null) { http_response_code(404); throw new RuntimeException('Markt niet gevonden.'); }
        echo json_encode(['ok'=>true,'market'=>$market,'offers'=>$service->offers($key,100),'history'=>$service->history($key,30)], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        exit;
    }
    $rows = $service->search([
        'q'=>$_GET['q']??'', 'sort'=>$_GET['sort']??'activity', 'side'=>$_GET['side']??'',
        'confidence'=>$_GET['confidence']??0, 'liquidity'=>$_GET['liquidity']??0,
    ], (int)($_GET['limit']??100));
    echo json_encode(['ok'=>true,'count'=>count($rows),'markets'=>$rows], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
