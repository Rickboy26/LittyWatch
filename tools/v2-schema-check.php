<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/V2/bootstrap-db.php';

use LittyWatch\V2\Core\Database;

header('Content-Type: application/json; charset=utf-8');
$pdo = Database::connection();
$tables = ['messages', 'offers', 'structured_offers', 'watchlist', 'market_snapshots', 'alerts'];
$result = [];

foreach ($tables as $table) {
    $exists = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($table))->fetchColumn();
    $columns = [];
    if ($exists) {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $row) {
            $columns[] = $row['name'];
        }
    }
    $result[$table] = ['exists' => $exists, 'columns' => $columns];
}

echo json_encode([
    'ok' => true,
    'version' => '2.0.1-schema-compatibility',
    'tables' => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
