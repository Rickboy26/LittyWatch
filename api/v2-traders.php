<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');


use LittyWatch\Infrastructure\Database;
use LittyWatch\Trader\TraderIntelligenceService;

try {
    $root = dirname(__DIR__);
    $pdo = Database::connect($root);
    $service = new TraderIntelligenceService($pdo);

    $player = trim((string)($_GET['player'] ?? ''));
    if ($player !== '') {
        $profile = $service->profile($player);
        if ($profile === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Trader niet gevonden.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'profile' => $profile,
            'markets' => $service->topMarkets($player, 25),
            'offers' => $service->recentOffers($player, 100),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'counts' => $service->counts(),
        'traders' => $service->search(
            (string)($_GET['q'] ?? ''),
            (string)($_GET['sort'] ?? 'activity'),
            min(500, max(1, (int)($_GET['limit'] ?? 100)))
        ),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
