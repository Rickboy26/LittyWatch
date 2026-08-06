<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/V2/bootstrap-db.php';

use LittyWatch\V2\Core\Database;

$pdo = Database::connection();
$tables = ['messages','offers','structured_offers','watchlist','market_snapshots','alert_rules'];
foreach ($tables as $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:name");
    $stmt->execute([':name' => $table]);
    echo str_pad($table, 22) . ((int)$stmt->fetchColumn() === 1 ? "OK\n" : "ONTBREEKT\n");
}
