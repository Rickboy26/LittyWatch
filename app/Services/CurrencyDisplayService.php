<?php
declare(strict_types=1);

namespace LittyWatch\Services;

final class CurrencyDisplayService
{
    private float $ectoPerArmbrace = 25.0;

    public function __construct(private readonly ExchangeRateService $exchangeRates)
    {
        $current = $exchangeRates->current();
        foreach ($current['rates'] as $rate) {
            if (($rate['left_unit'] ?? '') === 'Ecto' && ($rate['right_unit'] ?? '') === 'Arm') {
                $left = (float)($rate['left_amount'] ?? 0);
                $right = (float)($rate['right_amount'] ?? 0);
                if ($left > 0 && $right > 0) {
                    $this->ectoPerArmbrace = $left / $right;
                }
            }
        }
    }

    public function ectoPerArmbrace(): float
    {
        return $this->ectoPerArmbrace;
    }

    /** @return array{ecto:float,armbrace:float} */
    public function fromEcto(float $ecto): array
    {
        return [
            'ecto' => $ecto,
            'armbrace' => $this->ectoPerArmbrace > 0 ? $ecto / $this->ectoPerArmbrace : 0.0,
        ];
    }

    public function formatDual(?float $ecto, int $ectoDecimals = 2, int $armDecimals = 2): string
    {
        if ($ecto === null) {
            return '—';
        }
        $values = $this->fromEcto($ecto);
        return number_format($values['ecto'], $ectoDecimals, ',', '.') . 'e'
            . ' · '
            . number_format($values['armbrace'], $armDecimals, ',', '.') . 'a';
    }
}
