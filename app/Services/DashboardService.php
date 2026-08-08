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
        // Public dashboard deliberately ignores admin filters: it is a clean,
        // trusted live market snapshot. Search/review tooling lives elsewhere.
        $offers = $this->market->latestDashboardOffers(min(40, max(20, $limit)));
        $averages = $this->market->dashboardAveragePrices(array_column($offers, 'item'));
        foreach ($offers as &$offer) {
            $offer['average_price_ecto'] = $averages[mb_strtolower((string)$offer['item'])] ?? null;
        }
        unset($offer);

        $exchange = $this->exchangeRates->current();
        $quotes = $this->market->marketExchangeQuotes();
        $liveCount = 0;
        $latest = null;

        foreach ($exchange['rates'] as &$rate) {
            $key = (string)($rate['key'] ?? '');
            $rate['live'] = false;
            $rate['samples'] = 0;

            $quoteKey = match ($key) {
                'gold_to_ecto' => 'platinum_ecto',
                'ecto_to_armbrace' => 'ecto_arm',
                'ecto_to_zkey' => 'ecto_zkey',
                'ecto_to_obby' => 'ecto_obby',
                default => null,
            };
            if ($quoteKey === null || !isset($quotes[$quoteKey])) {
                continue;
            }

            $quote = $quotes[$quoteKey];
            $value = (float)($quote['value'] ?? 0);
            if ($value <= 0) {
                continue;
            }

            // Keep every card in the familiar Guild Wars trading direction.
            if ($key === 'gold_to_ecto') {
                $rate['left_amount'] = 100.0;
                $rate['right_amount'] = $value;      // 100k = ~6 ecto
            } elseif ($key === 'ecto_to_armbrace') {
                $rate['left_amount'] = $value;       // ~26e = 1 arm
                $rate['right_amount'] = 1.0;
            } elseif (in_array($key, ['ecto_to_zkey','ecto_to_obby'], true)) {
                $rate['left_amount'] = 1.0;
                $rate['right_amount'] = $value;      // 1e = X items
            }

            $rate['live'] = true;
            $rate['samples'] = (int)($quote['samples'] ?? 0);
            $liveCount++;
            $posted = trim((string)($quote['updated_at'] ?? ''));
            if ($posted !== '' && ($latest === null || strtotime($posted) > strtotime($latest))) {
                $latest = $posted;
            }
        }
        unset($rate);

        $exchange['source'] = $liveCount === count($exchange['rates'])
            ? 'Live mediaan uit betrouwbare Kamadan-data'
            : ($liveCount > 0 ? 'Live waar genoeg data beschikbaar is' : 'Veilige fallback · nog te weinig Kamadan-data');
        if ($latest !== null) {
            $exchange['updated_at'] = $latest;
        }

        $counters = $this->market->counters();

        return [
            'counters' => $counters,
            'offers' => $offers,
            'movers' => $this->market->dailyMovers(),
            'exchangeRates' => $exchange,
            'query' => $query,
            'type' => $type,
            'status' => $status,
            'limit' => $limit,
        ];
    }
}
