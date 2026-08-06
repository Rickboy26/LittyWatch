<?php
declare(strict_types=1);

namespace LittyWatch\Services;

use LittyWatch\Repositories\MarketRepository;

final class DashboardService
{
    public function __construct(
        private readonly MarketRepository $market,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    /** @return array<string,mixed> */
    public function build(string $query = '', string $type = '', string $status = '', int $limit = 150): array
    {
        return [
            'counters' => $this->market->counters(),
            'offers' => $this->market->latestOffers($query, $type, $status, $limit),
            'flips' => \flipOpportunities(),
            'exchangeRates' => $this->exchangeRates->current(),
            'query' => $query,
            'type' => $type,
            'status' => $status,
            'limit' => $limit,
        ];
    }
}
