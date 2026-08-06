<?php
declare(strict_types=1);

namespace LittyWatch\Services;

final class ExchangeRateService
{
    public function __construct(private readonly string $configFile) {}

    /** @return array{updated_at:string,source:string,rates:list<array<string,mixed>>} */
    public function current(): array
    {
        $config = is_file($this->configFile) ? require $this->configFile : [];
        $rates = [];
        foreach (($config['rates'] ?? []) as $key => $rate) {
            if (!is_array($rate)) {
                continue;
            }
            $rates[] = [
                'key' => (string)$key,
                'label' => (string)($rate['label'] ?? $key),
                'left_amount' => (float)($rate['left_amount'] ?? 0),
                'left_unit' => (string)($rate['left_unit'] ?? ''),
                'right_amount' => (float)($rate['right_amount'] ?? 0),
                'right_unit' => (string)($rate['right_unit'] ?? ''),
            ];
        }

        return [
            'updated_at' => (string)($config['updated_at'] ?? '-'),
            'source' => (string)($config['source'] ?? 'Onbekend'),
            'rates' => $rates,
        ];
    }
}
