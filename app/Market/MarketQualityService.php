<?php
declare(strict_types=1);

namespace LittyWatch\Market;

final class MarketQualityService
{
    public function score(array $stats): array
    {
        $traders = (int)($stats['unique_traders'] ?? 0);
        $samples = (int)($stats['active_samples'] ?? $stats['samples'] ?? 0);
        $buyCount = (int)($stats['buy_count'] ?? $stats['buys'] ?? 0);
        $sellCount = (int)($stats['sell_count'] ?? $stats['sells'] ?? 0);
        $dispersion = $this->dispersion($stats);
        $reviewShare = (float)($stats['review_share'] ?? 0.0);

        $data = min(100, $traders * 12 + min(35, $samples * 3) + (($buyCount > 0 && $sellCount > 0) ? 15 : 0) - (int)round($reviewShare * 25));
        $liquidity = min(100, $traders * 15 + min(40, $samples * 4));
        $certainty = max(0, min(100, 100 - (int)round($dispersion * 100) - (int)round($reviewShare * 40) + (($buyCount >= 2 && $sellCount >= 2) ? 10 : 0)));
        $overall = (int)round($data * .4 + $liquidity * .3 + $certainty * .3);

        return [
            'score' => $overall,
            'label' => $this->label($overall),
            'data_quality' => $this->label($data),
            'liquidity' => $this->label($liquidity),
            'price_certainty' => $this->label($certainty),
            'dispersion' => $dispersion,
        ];
    }

    private function dispersion(array $stats): float
    {
        $ranges = [];
        foreach ([['buy_min','buy_max','buy_median'],['sell_min','sell_max','sell_median']] as [$min,$max,$median]) {
            $m = isset($stats[$median]) ? (float)$stats[$median] : 0.0;
            if ($m > 0 && isset($stats[$min],$stats[$max])) $ranges[] = max(0.0, ((float)$stats[$max] - (float)$stats[$min]) / $m);
        }
        return $ranges ? min(1.0, array_sum($ranges) / count($ranges)) : 1.0;
    }

    private function label(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Hoog',
            $score >= 60 => 'Goed',
            $score >= 40 => 'Redelijk',
            $score >= 20 => 'Laag',
            default => 'Zeer laag',
        };
    }
}
