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
        $offers=$this->market->latestOffers($query,$type,$status,$limit);
        $averages=$this->market->dashboardAveragePrices(array_column($offers,'item'));
        foreach($offers as&$offer)$offer['average_price_ecto']=$averages[mb_strtolower((string)$offer['item'])]??null;
        unset($offer);

        $exchange=$this->exchangeRates->current();
        $quotes=$this->market->marketExchangeQuotes();
        foreach($exchange['rates'] as&$rate){
            $key=(string)($rate['key']??'');
            if($key==='gold_to_ecto'&&isset($quotes['platinum_ecto'])){$rate['left_amount']=$quotes['platinum_ecto'];$rate['right_amount']=1;}
            if($key==='ecto_to_armbrace'&&isset($quotes['ecto_arm'])){$rate['left_amount']=$quotes['ecto_arm'];$rate['right_amount']=1;}
            if($key==='ecto_to_zkey'&&isset($quotes['ecto_zkey'])){$rate['left_amount']=$quotes['ecto_zkey'];$rate['right_amount']=1;}
            if($key==='ecto_to_obby'&&isset($quotes['ecto_obby'])){$rate['left_amount']=$quotes['ecto_obby'];$rate['right_amount']=1;}
        }
        unset($rate);
        if($quotes){$exchange['source']='Automatisch uit betrouwbare Kamadan-data';$exchange['updated_at']=date('Y-m-d H:i');}

        return [
            'counters' => $this->market->counters(),
            'offers' => $offers,
            'movers' => $this->market->dailyMovers(),
            'exchangeRates' => $exchange,
            'query' => $query,'type' => $type,'status' => $status,'limit' => $limit,
        ];
    }
}
