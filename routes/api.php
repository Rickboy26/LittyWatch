<?php
declare(strict_types=1);

use LittyWatch\Controllers\ApiController;
use LittyWatch\Controllers\MaintenanceController;
use LittyWatch\Core\Container;
use LittyWatch\Core\Router;

return static function (Router $router, Container $container): void {
    $router->get('/api/dashboard', fn($request) => $container->get(ApiController::class)->dashboard($request));
    $router->get('/api/live', fn($request) => $container->get(MaintenanceController::class)->liveApi($request));
};
