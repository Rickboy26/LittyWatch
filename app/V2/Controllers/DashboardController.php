<?php
declare(strict_types=1);

namespace LittyWatch\V2\Controllers;

use LittyWatch\V2\Core\View;
use LittyWatch\V2\Repositories\DashboardRepository;
use LittyWatch\V2\Services\ExchangeRateService;

final class DashboardController
{
    public function index(): void
    {
        $repository = new DashboardRepository();
        $exchange = new ExchangeRateService();

        View::render('dashboard', [
            'stats' => $repository->stats(),
            'markets' => $repository->topMarkets(8),
            'offers' => $repository->latestOffers(12),
            'rates' => $exchange->rates(),
            'features' => require dirname(__DIR__, 3) . '/config/features.php',
        ]);
    }
}
