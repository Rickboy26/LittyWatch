<?php
declare(strict_types=1);

require __DIR__ . '/app/V2/bootstrap-db.php';

use LittyWatch\V2\Core\Database;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connection();
    $sql = file_get_contents(__DIR__ . '/database/migrations/200_v2_foundation.sql');
    if ($sql === false) {
        throw new RuntimeException('Migratiebestand ontbreekt.');
    }
    $pdo->exec($sql);
    echo json_encode([
        'ok' => true,
        'version' => '2.0-foundation',
        'message' => 'V2-tabellen zijn aangemaakt of bestonden al.',
        'next' => '/v2.php',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
