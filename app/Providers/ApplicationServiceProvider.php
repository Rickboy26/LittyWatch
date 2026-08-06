<?php
declare(strict_types=1);

namespace LittyWatch\Providers;

use LittyWatch\Controllers\AdminController;
use LittyWatch\Controllers\AlertController;
use LittyWatch\Controllers\ApiController;
use LittyWatch\Controllers\DashboardController;
use LittyWatch\Controllers\WatchlistController;
use LittyWatch\Controllers\ItemController;
use LittyWatch\Controllers\KnowledgeController;
use LittyWatch\Controllers\MaintenanceController;
use LittyWatch\Controllers\LiveController;
use LittyWatch\Controllers\TraderController;
use LittyWatch\Controllers\TrendController;
use LittyWatch\Controllers\IntelligenceController;
use LittyWatch\Controllers\SystemController;
use LittyWatch\Controllers\AssetController;
use LittyWatch\Controllers\ParserReviewController;
use LittyWatch\Controllers\StructuredMarketController;
use LittyWatch\Controllers\StructuredOfferController;
use LittyWatch\Core\Container;
use LittyWatch\Core\ErrorHandler;
use LittyWatch\Core\View;
use LittyWatch\Knowledge\KnowledgeBase;
use LittyWatch\Knowledge\KnowledgeControllerData;
use LittyWatch\Knowledge\Schema;
use LittyWatch\Repositories\MarketRepository;
use LittyWatch\Repositories\AlertRepository;
use LittyWatch\Repositories\WatchlistRepository;
use LittyWatch\Repositories\PlatformRepository;
use LittyWatch\Repositories\ParserReviewRepository;
use LittyWatch\Repositories\StructuredMarketRepository;
use LittyWatch\Repositories\StructuredOfferRepository;
use LittyWatch\Services\CurrencyDisplayService;
use LittyWatch\Services\AlertService;
use LittyWatch\Services\WatchlistService;
use LittyWatch\Services\DashboardService;
use LittyWatch\Services\ExchangeRateService;
use LittyWatch\Services\ItemImageService;
use LittyWatch\Support\ProjectPaths;

final class ApplicationServiceProvider
{
    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly string $root,
        private readonly array $config,
    ) {
    }

    public function register(Container $container): void
    {
        $paths = new ProjectPaths($this->root);
        $runtimeConfig = $this->config + [
            'debug' => false,
            'log_path' => $paths->logs('application.log'),
        ];

        $container->singleton(ProjectPaths::class, fn() => $paths);
        $container->singleton('config', fn() => $runtimeConfig);
        $container->singleton('pdo', fn() => db());
        $container->singleton(ErrorHandler::class, fn() => new ErrorHandler($runtimeConfig));
        $container->singleton(View::class, fn() => new View($paths->views()));

        $container->singleton(MarketRepository::class, fn(Container $c) => new MarketRepository($c->get('pdo')));
        $container->singleton(StructuredOfferRepository::class, fn(Container $c) => new StructuredOfferRepository($c->get('pdo')));
        $container->singleton(ParserReviewRepository::class, fn(Container $c) => new ParserReviewRepository($c->get('pdo')));
        $container->singleton(StructuredMarketRepository::class, fn(Container $c) => new StructuredMarketRepository($c->get('pdo')));
        $container->singleton(AlertRepository::class, fn(Container $c) => new AlertRepository($c->get('pdo')));
        $container->singleton(WatchlistRepository::class, fn(Container $c) => new WatchlistRepository($c->get('pdo')));
        $container->singleton(PlatformRepository::class, fn(Container $c) => new PlatformRepository($c->get('pdo')));

        $container->singleton(ExchangeRateService::class, fn() => new ExchangeRateService($paths->config('exchange-rates.php')));
        $container->singleton(CurrencyDisplayService::class, fn(Container $c) => new CurrencyDisplayService($c->get(ExchangeRateService::class)));
        $container->singleton(ItemImageService::class, fn() => new ItemImageService($this->root));
        $container->singleton(DashboardService::class, fn(Container $c) => new DashboardService($c->get(MarketRepository::class), $c->get(ExchangeRateService::class)));
        $container->singleton(AlertService::class, fn(Container $c) => new AlertService($c->get(AlertRepository::class)));
        $container->singleton(WatchlistService::class, fn(Container $c) => new WatchlistService($c->get(WatchlistRepository::class), $c->get(AlertService::class)));

        $container->singleton(DashboardController::class, fn(Container $c) => new DashboardController($c->get(DashboardService::class), $c->get(View::class)));
        $container->singleton(ApiController::class, fn(Container $c) => new ApiController($c->get(DashboardService::class)));
        $container->singleton(ItemController::class, fn(Container $c) => new ItemController($c->get(MarketRepository::class), $c->get(View::class)));
        $container->singleton(StructuredOfferController::class, fn(Container $c) => new StructuredOfferController($c->get(StructuredOfferRepository::class), $c->get(View::class)));
        $container->singleton(ParserReviewController::class, fn(Container $c) => new ParserReviewController($c->get(ParserReviewRepository::class), $c->get(View::class)));
        $container->singleton(StructuredMarketController::class, fn(Container $c) => new StructuredMarketController($c->get(StructuredMarketRepository::class), $c->get(View::class), $c->get(CurrencyDisplayService::class)));
        $container->singleton(AdminController::class, fn(Container $c) => new AdminController($c->get(View::class), $c->get(ItemImageService::class)));
        $container->singleton(WatchlistController::class, fn(Container $c) => new WatchlistController($c->get(WatchlistService::class), $c->get(View::class)));
        $container->singleton(AlertController::class, fn(Container $c) => new AlertController($c->get(AlertService::class), $c->get(WatchlistRepository::class), $c->get(CurrencyDisplayService::class), $c->get(View::class)));
        $container->singleton(MaintenanceController::class, fn(Container $c) => new MaintenanceController($c->get('pdo'), $c->get(View::class), $this->root));
        $container->singleton(LiveController::class, fn(Container $c) => new LiveController($c->get(PlatformRepository::class), $c->get(View::class)));
        $container->singleton(TraderController::class, fn(Container $c) => new TraderController($c->get(PlatformRepository::class), $c->get(View::class)));
        $container->singleton(TrendController::class, fn(Container $c) => new TrendController($c->get(PlatformRepository::class), $c->get(View::class)));
        $container->singleton(IntelligenceController::class, fn(Container $c) => new IntelligenceController($c->get(PlatformRepository::class), $c->get(View::class)));
        $container->singleton(SystemController::class, fn(Container $c) => new SystemController($c->get(PlatformRepository::class), $c->get(ProjectPaths::class), $c->get(View::class)));
        $container->singleton(AssetController::class, fn(Container $c) => new AssetController($c->get(PlatformRepository::class), $c->get(ProjectPaths::class), $c->get(View::class)));

        $container->singleton(KnowledgeBase::class, function (Container $c): KnowledgeBase {
            Schema::install($c->get('pdo'));
            return new KnowledgeBase($c->get('pdo'));
        });
        $container->singleton(KnowledgeControllerData::class, fn(Container $c) => new KnowledgeControllerData($c->get(KnowledgeBase::class)));
        $container->singleton(KnowledgeController::class, fn(Container $c) => new KnowledgeController($c->get(KnowledgeControllerData::class), $c->get(View::class)));
    }
}
