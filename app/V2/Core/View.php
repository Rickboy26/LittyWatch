<?php
declare(strict_types=1);

namespace LittyWatch\V2\Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        $path = dirname(__DIR__) . '/Views/' . $view . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('View ontbreekt: ' . $view);
        }
        extract($data, EXTR_SKIP);
        require $path;
    }
}
