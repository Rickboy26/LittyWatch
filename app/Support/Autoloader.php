<?php
declare(strict_types=1);

namespace LittyWatch\Support;

final class Autoloader
{
    public static function register(string $baseDir): void
    {
        spl_autoload_register(static function (string $class) use ($baseDir): void {
            $prefix = 'LittyWatch\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $path = rtrim($baseDir, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relative)
                . '.php';

            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}
