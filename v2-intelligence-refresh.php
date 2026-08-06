<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Intelligence/Schema.php';
require __DIR__ . '/app/V2/Intelligence/MarketIntelligenceService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Intelligence\MarketIntelligenceService;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connect(__DIR__);
    $result = (new MarketIntelligenceService($pdo))->rebuild();

    echo json_encode([
        'ok' => true,
        'version' => '2.6.1-intelligence-rebuild',
        ...$result,
        'note' => 'Lifecycle-status is genegeerd; nieuwste offer per trader, markt en koop/verkoopzijde telt.',
        'next' => '/v2-intelligence.php',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'version' => '2.6.1-intelligence-rebuild',
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
