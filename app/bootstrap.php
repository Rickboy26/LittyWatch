<?php
declare(strict_types=1);

use LittyWatch\Controllers\ApiController;
use LittyWatch\Controllers\DashboardController;
use LittyWatch\Controllers\ItemController;
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
$container->singleton(ApiController::class, fn(Container $c) => new ApiController($c->get(DashboardService::class)));
$container->singleton(ItemController::class, fn(Container $c) => new ItemController($c->get(MarketRepository::class), $c->get(View::class)));

$router = new Router();
$router->get('/', fn($request) => $container->get(DashboardController::class)->index($request));
$router->get('/api/dashboard', fn($request) => $container->get(ApiController::class)->dashboard($request));
$router->get('/items', fn($request) => $container->get(ItemController::class)->index($request));
$router->get('/item', fn($request) => $container->get(ItemController::class)->show($request));

return new Application($container, $router);
