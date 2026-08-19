<?php
declare(strict_types=1);

namespace LittyWatch\Infrastructure;

use PDO;

/** Compatibility facade for API/cron services. Database ownership lives in bootstrap::db(). */
final class Database
{
    public static function connect(?string $root = null): PDO
    {
        return \db();
    }
}
