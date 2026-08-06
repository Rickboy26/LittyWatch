<?php
declare(strict_types=1);

use LittyWatch\Controllers\ApiController;
use LittyWatch\Controllers\AdminController;
use LittyWatch\Controllers\DashboardController;
use LittyWatch\Controllers\ItemController;
use LittyWatch\Controllers\KnowledgeController;
use LittyWatch\Controllers\StructuredOfferController;
use LittyWatch\Controllers\ParserReviewController;
use LittyWatch\Controllers\StructuredMarketController;
use LittyWatch\Core\Application;
use LittyWatch\Core\Container;
use LittyWatch\Core\Router;
use LittyWatch\Core\View;
use LittyWatch\Repositories\MarketRepository;
use LittyWatch\Repositories\StructuredOfferRepository;
use LittyWatch\Repositories\ParserReviewRepository;
use LittyWatch\Repositories\StructuredMarketRepository;
use LittyWatch\Services\DashboardService;
use LittyWatch\Services\ExchangeRateService;
use LittyWatch\Services\CurrencyDisplayService;
use LittyWatch\Services\ItemImageService;
use LittyWatch\Knowledge\KnowledgeBase;
use LittyWatch\Knowledge\KnowledgeControllerData;
use LittyWatch\Knowledge\Schema;

require_once dirname(__DIR__) . '/bootstrap.php';
installSchema();

$container = new Container();
$container->singleton('config', fn() => $config + ['debug' => true]);
$container->singleton('pdo', fn() => db());
$container->singleton(View::class, fn() => new View(__DIR__ . '/Views'));
$container->singleton(MarketRepository::class, fn(Container $c) => new MarketRepository($c->get('pdo')));
$container->singleton(StructuredOfferRepository::class, fn(Container $c) => new StructuredOfferRepository($c->get('pdo')));
$container->singleton(StructuredOfferController::class, fn(Container $c) => new StructuredOfferController($c->get(StructuredOfferRepository::class), $c->get(View::class)));
$container->singleton(ParserReviewRepository::class, fn(Container $c) => new ParserReviewRepository($c->get('pdo')));
$container->singleton(ParserReviewController::class, fn(Container $c) => new ParserReviewController($c->get(ParserReviewRepository::class), $c->get(View::class)));
$container->singleton(StructuredMarketRepository::class, fn(Container $c) => new StructuredMarketRepository($c->get('pdo')));
$container->singleton(StructuredMarketController::class, fn(Container $c) => new StructuredMarketController($c->get(StructuredMarketRepository::class), $c->get(View::class), $c->get(CurrencyDisplayService::class)));
$container->singleton(ExchangeRateService::class, fn() => new ExchangeRateService(dirname(__DIR__) . '/config/exchange-rates.php'));
$container->singleton(CurrencyDisplayService::class, fn(Container $c) => new CurrencyDisplayService($c->get(ExchangeRateService::class)));
$container->singleton(ItemImageService::class, fn() => new ItemImageService(dirname(__DIR__)));
$container->singleton(AdminController::class, fn(Container $c) => new AdminController($c->get(View::class), $c->get(ItemImageService::class)));
$container->singleton(DashboardService::class, fn(Container $c) => new DashboardService($c->get(MarketRepository::class), $c->get(ExchangeRateService::class)));
$container->singleton(DashboardController::class, fn(Container $c) => new DashboardController($c->get(DashboardService::class), $c->get(View::class)));
$container->singleton(ApiController::class, fn(Container $c) => new ApiController($c->get(DashboardService::class)));
$container->singleton(ItemController::class, fn(Container $c) => new ItemController($c->get(MarketRepository::class), $c->get(View::class)));
$container->singleton(KnowledgeBase::class, function(Container $c){ Schema::install($c->get('pdo')); return new KnowledgeBase($c->get('pdo')); });
$container->singleton(KnowledgeControllerData::class, fn(Container $c) => new KnowledgeControllerData($c->get(KnowledgeBase::class)));
$container->singleton(KnowledgeController::class, fn(Container $c) => new KnowledgeController($c->get(KnowledgeControllerData::class), $c->get(View::class)));

$router = new Router();
$router->get('/', fn($request) => $container->get(DashboardController::class)->index($request));
$router->get('/api/dashboard', fn($request) => $container->get(ApiController::class)->dashboard($request));
$router->get('/items', fn($request) => $container->get(ItemController::class)->index($request));
$router->get('/item', fn($request) => $container->get(ItemController::class)->show($request));
$router->get('/knowledge', fn($request) => $container->get(KnowledgeController::class)->index($request));
$router->get('/structured-offers', fn($request) => $container->get(StructuredOfferController::class)->index($request));
$router->get('/parser-review', fn($request) => $container->get(ParserReviewController::class)->index($request));
$router->post('/parser-review', fn($request) => $container->get(ParserReviewController::class)->update($request));
$router->get('/parser-review/export', fn($request) => $container->get(ParserReviewController::class)->export($request));
$router->get('/markets', fn($request) => $container->get(StructuredMarketController::class)->index($request));
$router->get('/market', fn($request) => $container->get(StructuredMarketController::class)->show($request));
$router->get('/admin', fn($request) => $container->get(AdminController::class)->index($request));

return new Application($container, $router);
