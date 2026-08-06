<?php
declare(strict_types=1);

/**
 * LittyWatch V2.8.1 SQLite migration fix
 *
 * Fix:
 * SQLite does not allow non constant defaults when adding columns.
 * This migration adds columns without CURRENT_TIMESTAMP defaults.
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/app/V2/Schema.php';

    $pdo = Schema::pdo();

    $tables = [
        'watchlist' => [
            'updated_at' => "TEXT DEFAULT ''"
        ],
        'alerts' => [
            'updated_at' => "TEXT DEFAULT ''",
            'last_triggered_at' => "TEXT DEFAULT ''"
        ]
    ];

    foreach ($tables as $table => $columns) {
        $existing = [];
        foreach ($pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $existing[$column['name']] = true;
        }

        foreach ($columns as $name => $definition) {
            if (!isset($existing[$name])) {
                $pdo->exec(
                    "ALTER TABLE {$table} ADD COLUMN {$name} {$definition}"
                );
            }
        }

        // Fill empty timestamps after adding columns
        foreach ($columns as $name => $_) {
            $pdo->exec(
                "UPDATE {$table}
                 SET {$name} = CURRENT_TIMESTAMP
                 WHERE {$name} = '' OR {$name} IS NULL"
            );
        }
    }

    echo json_encode([
        'ok' => true,
        'version' => '2.8.1-sqlite-hotfix'
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
