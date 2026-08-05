<?php
declare(strict_types=1);

namespace LittyWatch\Services;

use LittyWatch\Repositories\MarketRepository;

final class DashboardService
{
    public function __construct(private readonly MarketRepository $market) {}

    /** @return array<string,mixed> */
    public function build(string $query = '', string $type = ''): array
    {
        return [
            'counters' => $this->market->counters(),
            'offers' => $this->market->latestOffers($query, $type),
            'flips' => \flipOpportunities(),
            'query' => $query,
            'type' => $type,
        ];
    }
}
