<?php
declare(strict_types=1);

use LittyWatch\Core\Application;
use LittyWatch\Core\Container;
use LittyWatch\Core\ErrorHandler;
use LittyWatch\Core\Router;
use LittyWatch\Providers\ApplicationServiceProvider;
use LittyWatch\Providers\RouteServiceProvider;
use LittyWatch\Support\ProjectPaths;

$root = dirname(__DIR__);
require_once $root . '/bootstrap.php';
installSchema();

$container = new Container();
(new ApplicationServiceProvider($root, $config))->register($container);

$router = new Router();
(new RouteServiceProvider())->register(
    $router,
    $container,
    $container->get(ProjectPaths::class),
);

return new Application(
    $router,
    $container->get(ErrorHandler::class),
);
