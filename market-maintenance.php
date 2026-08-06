<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/app/Market/OfferLifecycleService.php';

installSchema();

$service = new \LittyWatch\Market\OfferLifecycleService(db());
$result = $service->rebuild();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'version' => 'v1.8.1',
    'message' => 'Market lifecycle rebuilt',
    'result' => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
