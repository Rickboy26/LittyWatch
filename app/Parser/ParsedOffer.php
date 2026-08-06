<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class ParsedOffer
{
    public function __construct(
        public readonly string $tradeType,
        public readonly string $item,
        public readonly string $itemKey,
        public readonly array $modifiers,
        public readonly ParsedPrice $price,
        public readonly float $confidence,
        public readonly string $status,
        public readonly string $reason,
        public readonly string $segment,
        public readonly array $tokens = [],
        public readonly array $profile = [],
        public readonly array $relevantProperties = [],
        public readonly string $marketKey = '',
        public readonly ?array $exchange = null,
    ) {}

    public function toArray(): array
    {
        return [
            'trade_type' => $this->tradeType,
            'item' => $this->item,
            'item_key' => $this->itemKey,
            'modifiers' => $this->modifiers,
            'price' => $this->price->toArray(),
            'confidence' => $this->confidence,
            'status' => $this->status,
            'reason' => $this->reason,
            'segment' => $this->segment,
            'tokens' => $this->tokens,
            'profile' => $this->profile,
            'relevant_properties' => $this->relevantProperties,
            'market_key' => $this->marketKey,
            'exchange' => $this->exchange,
        ];
    }
}
