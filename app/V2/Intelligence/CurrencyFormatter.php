<?php

declare(strict_types=1);

namespace LittyWatch\V2\Intelligence;

final class CurrencyFormatter
{
    private float $ectoPerArmbrace = 25.0;

    public function __construct(string $root)
    {
        $file = $root . '/config/exchange-rates.php';
        if (!is_file($file)) {
            return;
        }
        $rates = require $file;
        foreach ((array)$rates as $rate) {
            if (!is_array($rate)) {
                continue;
            }
            $leftUnit = strtolower((string)($rate['left_unit'] ?? ''));
            $rightUnit = strtolower((string)($rate['right_unit'] ?? ''));
            $left = (float)($rate['left_amount'] ?? 0);
            $right = (float)($rate['right_amount'] ?? 0);
            if ($left > 0 && $right > 0 && str_contains($leftUnit, 'ecto') && str_contains($rightUnit, 'arm')) {
                $this->ectoPerArmbrace = $left / $right;
                break;
            }
            if ($left > 0 && $right > 0 && str_contains($leftUnit, 'arm') && str_contains($rightUnit, 'ecto')) {
                $this->ectoPerArmbrace = $right / $left;
                break;
            }
        }
    }

    public function ecto(?float $value): string
    {
        return $value === null ? '—' : $this->number($value) . 'e';
    }

    public function armbrace(?float $value): string
    {
        if ($value === null || $this->ectoPerArmbrace <= 0) {
            return '—';
        }
        return $this->number($value / $this->ectoPerArmbrace) . 'a';
    }

    private function number(float $value): string
    {
        $decimals = abs($value - round($value)) < 0.005 ? 0 : 2;
        return number_format($value, $decimals, ',', '.');
    }
}
