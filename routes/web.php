<?php
declare(strict_types=1);

use LittyWatch\Controllers\AdminController;
use LittyWatch\Controllers\AlertController;
use LittyWatch\Controllers\DashboardController;
use LittyWatch\Controllers\WatchlistController;
use LittyWatch\Controllers\ItemController;
use LittyWatch\Controllers\KnowledgeController;
use LittyWatch\Controllers\MaintenanceController;
use LittyWatch\Controllers\PageController;
use LittyWatch\Controllers\ParserReviewController;
use LittyWatch\Controllers\StructuredMarketController;
use LittyWatch\Controllers\StructuredOfferController;
use LittyWatch\Core\Container;
use LittyWatch\Core\Router;

return static function (Router $router, Container $container): void {
    $router->get('/', fn($request) => $container->get(DashboardController::class)->index($request));

    $router->get('/items', fn($request) => $container->get(ItemController::class)->index($request));
    $router->get('/item', fn($request) => $container->get(ItemController::class)->show($request));
    $router->get('/markets', fn($request) => $container->get(StructuredMarketController::class)->index($request));
    $router->get('/market', fn($request) => $container->get(StructuredMarketController::class)->show($request));
    $router->get('/structured-offers', fn($request) => $container->get(StructuredOfferController::class)->index($request));
    $router->get('/knowledge', fn($request) => $container->get(KnowledgeController::class)->index($request));

    $router->get('/parser-review', fn($request) => $container->get(ParserReviewController::class)->index($request));
    $router->post('/parser-review', fn($request) => $container->get(ParserReviewController::class)->update($request));
    $router->get('/parser-review/export', fn($request) => $container->get(ParserReviewController::class)->export($request));

    foreach ([
        '/live' => 'live',
        '/traders' => 'traders',
        '/trader' => 'trader',
        '/trends' => 'trends',
        '/intelligence' => 'intelligence',
        '/game-assets' => 'assets',
        '/system' => 'system',
    ] as $path => $page) {
        $router->get($path, fn($request) => $container->get(PageController::class)->show($request, $page));
    }

    foreach (['/intelligence' => 'intelligence', '/game-assets' => 'assets'] as $path => $page) {
        $router->post($path, fn($request) => $container->get(PageController::class)->show($request, $page));
    }


    $router->get('/watchlist', fn($request) => $container->get(WatchlistController::class)->index($request));
    $router->post('/watchlist', fn($request) => $container->get(WatchlistController::class)->update($request));
    $router->get('/alerts', fn($request) => $container->get(AlertController::class)->index($request));
    $router->post('/alerts', fn($request) => $container->get(AlertController::class)->update($request));

    $router->get('/admin', fn($request) => $container->get(AdminController::class)->index($request));
    $router->get('/admin/collect', fn($request) => $container->get(MaintenanceController::class)->collect($request));
    $router->get('/admin/reparse', fn($request) => $container->get(MaintenanceController::class)->reparse($request));
    $router->get('/admin/market-maintenance', fn($request) => $container->get(MaintenanceController::class)->marketMaintenance($request));
    $router->get('/admin/knowledge-seed', fn($request) => $container->get(MaintenanceController::class)->seedKnowledge($request));
    $router->get('/admin/intelligence-refresh', fn($request) => $container->get(MaintenanceController::class)->intelligence($request));
    $router->get('/admin/snapshot', fn($request) => $container->get(MaintenanceController::class)->snapshot($request));
    $router->get('/admin/parser-lab', fn($request) => $container->get(MaintenanceController::class)->parserLab($request));
    $router->post('/admin/parser-lab', fn($request) => $container->get(MaintenanceController::class)->parserLab($request));
};
