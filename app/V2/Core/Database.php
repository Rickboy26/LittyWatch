<?php
declare(strict_types=1);

namespace LittyWatch\V2\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = require dirname(__DIR__, 3) . '/config/';
        $path = $config['database_path'] ?? null;
        if (!is_string($path) || $path === '') {
            throw new RuntimeException('V2 database_path ontbreekt.');
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Databasefolder kon niet worden aangemaakt.');
        }

        self::$pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo->exec('PRAGMA busy_timeout = 30000');
        // WAL lets the collector write while dashboard/admin readers stay responsive.
        // journal_mode itself can briefly be busy during deploy, so never fail a request on it.
        try { self::$pdo->exec('PRAGMA journal_mode = WAL'); } catch (\Throwable) {}
        try { self::$pdo->exec('PRAGMA synchronous = NORMAL'); } catch (\Throwable) {}
        return self::$pdo;
    }
}
