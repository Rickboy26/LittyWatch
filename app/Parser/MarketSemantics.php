<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

/**
 * Canonical market quote semantics for an item.
 *
 * Catalog metadata describes how traders normally quote an item, independently
 * from the syntax used in one specific Kamadan message. This keeps price parsing
 * deterministic while avoiding category-wide assumptions.
 */
final class MarketSemantics
{
    public function __construct(
        public readonly string $quoteBasis = 'unknown',
        public readonly float $quoteSize = 1.0,
        public readonly string $displayBasis = 'each',
    ) {}

    /** @param array<string,mixed> $item */
    public static function fromItem(array $item): self
    {
        // Phase 3H names; Phase 3G names remain supported for compatibility.
        $basis = strtolower(trim((string)($item['market_quote_basis'] ?? $item['market_price_basis'] ?? '')));
        if (!in_array($basis, ['each','stack','unknown'], true)) $basis = 'unknown';

        $size = (float)($item['market_quote_size'] ?? $item['market_stack_size'] ?? 0);
        if ($basis === 'stack' && $size <= 0) $size = 250.0;
        if ($basis === 'each') $size = 1.0;
        if ($size <= 0) $size = 1.0;

        $display = strtolower(trim((string)($item['market_display_basis'] ?? 'each')));
        if (!in_array($display, ['each','stack'], true)) $display = 'each';

        return new self($basis, $size, $display);
    }

    public function isStackQuoted(): bool
    {
        return $this->quoteBasis === 'stack' && $this->quoteSize > 1;
    }

    public function isEachQuoted(): bool
    {
        return $this->quoteBasis === 'each';
    }
}
