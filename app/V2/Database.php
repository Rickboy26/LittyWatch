<?php

declare(strict_types=1);

namespace LittyWatch\V2;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    public static function connect(?string $root = null): PDO
    {
        $root ??= dirname(__DIR__, 2);
        $path = $root . '/data/market.sqlite';

        if (!is_file($path)) {
            throw new RuntimeException('SQLite database niet gevonden: ' . $path);
        }

        try {
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 30000');
            try { $pdo->exec('PRAGMA journal_mode = WAL'); } catch (\Throwable) {}
            try { $pdo->exec('PRAGMA synchronous = NORMAL'); } catch (\Throwable) {}
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Databaseverbinding mislukt: ' . $e->getMessage(), 0, $e);
        }
    }
}
