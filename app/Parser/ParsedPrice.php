<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class ParsedPrice
{
    public function __construct(
        public readonly ?float $amount,
        public readonly ?string $currency,
        public readonly ?float $ectoValue,
        public readonly string $basis = 'unknown',
        public readonly ?float $quantity = null,
        public readonly ?float $unitEcto = null,
        public readonly ?string $raw = null,
    ) {}

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'ecto_value' => $this->ectoValue,
            'basis' => $this->basis,
            'quantity' => $this->quantity,
            'unit_ecto' => $this->unitEcto,
            'raw' => $this->raw,
        ];
    }
}
