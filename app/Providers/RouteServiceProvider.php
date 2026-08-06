<?php
declare(strict_types=1);

namespace LittyWatch\Providers;

use LittyWatch\Core\Container;
use LittyWatch\Core\Router;
use LittyWatch\Support\ProjectPaths;
use RuntimeException;

final class RouteServiceProvider
{
    public function register(Router $router, Container $container, ProjectPaths $paths): void
    {
        foreach (['web.php', 'api.php'] as $file) {
            $routeFile = $paths->root('routes/' . $file);
            if (!is_file($routeFile)) {
                throw new RuntimeException('Routebestand ontbreekt: ' . $routeFile);
            }

            $registrar = require $routeFile;
            if (!is_callable($registrar)) {
                throw new RuntimeException('Ongeldig routebestand: ' . $routeFile);
            }

            $registrar($router, $container);
        }
    }
}
