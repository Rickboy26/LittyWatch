<?php
declare(strict_types=1);

use LittyWatch\Controllers\DashboardController;
use LittyWatch\Core\Application;
use LittyWatch\Core\Container;
use LittyWatch\Core\Router;
use LittyWatch\Core\View;
use LittyWatch\Repositories\MarketRepository;
use LittyWatch\Services\DashboardService;

require_once dirname(__DIR__) . '/bootstrap.php';
installSchema();

$container = new Container();
$container->singleton('config', fn() => $config + ['debug' => true]);
$container->singleton('pdo', fn() => db());
$container->singleton(View::class, fn() => new View(__DIR__ . '/Views'));
$container->singleton(MarketRepository::class, fn(Container $c) => new MarketRepository($c->get('pdo')));
$container->singleton(DashboardService::class, fn(Container $c) => new DashboardService($c->get(MarketRepository::class)));
$container->singleton(DashboardController::class, fn(Container $c) => new DashboardController($c->get(DashboardService::class), $c->get(View::class)));

$router = new Router();
$router->get('/', fn($request) => $container->get(DashboardController::class)->index($request));

return new Application($container, $router);
