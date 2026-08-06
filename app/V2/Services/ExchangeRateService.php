<?php
declare(strict_types=1);

namespace LittyWatch\V2\Services;

final class ExchangeRateService
{
    public function rates(): array
    {
        $path = dirname(__DIR__, 3) . '/config/exchange-rates.php';
        if (is_file($path)) {
            $legacy = require $path;
            if (is_array($legacy)) {
                return $this->normalizeLegacy($legacy);
            }
        }

        return [
            ['left' => '100k', 'right' => '5 Ecto', 'icon' => 'gold'],
            ['left' => '25 Ecto', 'right' => '1 Arm', 'icon' => 'ecto'],
            ['left' => '1 Ecto', 'right' => '0,8 Zkey', 'icon' => 'zkey'],
            ['left' => '1 Ecto', 'right' => '2 Obby Shards', 'icon' => 'obby'],
        ];
    }

    private function normalizeLegacy(array $legacy): array
    {
        $rows = [];
        foreach ($legacy as $key => $rate) {
            if (!is_array($rate)) {
                continue;
            }
            $leftAmount = $this->formatNumber($rate['left_amount'] ?? 1);
            $rightAmount = $this->formatNumber($rate['right_amount'] ?? 1);
            $rows[] = [
                'left' => $leftAmount . ' ' . ($rate['left_unit'] ?? ''),
                'right' => $rightAmount . ' ' . ($rate['right_unit'] ?? ''),
                'icon' => (string) $key,
            ];
        }
        return $rows ?: $this->ratesFallback();
    }

    private function ratesFallback(): array
    {
        return [
            ['left' => '100k', 'right' => '5 Ecto', 'icon' => 'gold'],
            ['left' => '25 Ecto', 'right' => '1 Arm', 'icon' => 'ecto'],
        ];
    }

    private function formatNumber(mixed $value): string
    {
        $number = (float) $value;
        $decimals = abs($number - round($number)) < 0.000001 ? 0 : 2;
        return number_format($number, $decimals, ',', '.');
    }
}
