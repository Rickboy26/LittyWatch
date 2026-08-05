<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class PriceMatcher
{
    public function parse(string $segment): ParsedPrice
    {
        if (preg_match('/(?<!\d)(\d+(?:[.,]\d+)?)\s*:\s*(\d+(?:[.,]\d+)?)\s*(a|e|k)\b/i', $segment, $m)) {
            $quantity = $this->number($m[1]);
            $amount = $this->number($m[2]);
            return $this->make($amount, strtolower($m[3]), 'ratio', $quantity, $m[0]);
        }

        if (preg_match('/(?<!\d)(\d+(?:[.,]\d+)?)\s*=\s*(\d+(?:[.,]\d+)?)\s*(a|e|k)\b/i', $segment, $m)) {
            $quantity = $this->number($m[1]);
            $amount = $this->number($m[2]);
            return $this->make($amount, strtolower($m[3]), 'exchange', $quantity, $m[0]);
        }

        if (preg_match('/(?<![a-z0-9])(\d+(?:[.,]\d+)?)\s*(a|ambr(?:ace)?s?|armbraces?|e|ectos?|k|plat(?:inum)?)(?=\b|\/|$)/i', $segment, $m, PREG_OFFSET_CAPTURE)) {
            $amount = $this->number($m[1][0]);
            $currencyRaw = mb_strtolower($m[2][0]);
            $currency = str_starts_with($currencyRaw, 'a') ? 'a' : (str_starts_with($currencyRaw, 'e') ? 'e' : 'k');
            $tailOffset = $m[0][1] + strlen($m[0][0]);
            $tail = substr($segment, $tailOffset, 18);
            $basis = preg_match('/\/\s*(?:st|stk|stack)\b/i', $tail) ? 'stack'
                : (preg_match('/\/\s*(?:ea|each|e)\b/i', $tail) ? 'each' : 'total');
            $quantity = $basis === 'stack' ? 250.0 : $this->detectInventoryQuantity($segment);
            return $this->make($amount, $currency, $basis, $quantity, $m[0][0]);
        }

        return new ParsedPrice(null, null, null, 'unknown');
    }

    private function detectInventoryQuantity(string $segment): ?float
    {
        if (preg_match('/\[x\s*(\d+)\]/i', $segment, $m)) return (float) $m[1];
        if (preg_match('/\bx\s*(\d+)\b/i', $segment, $m)) return (float) $m[1];
        if (preg_match('/\b(\d+)\s+(?:gott?s?|go?t|nick\s*sets?|nicksets?|zkeys?|tomes?|unids?|gifts?)\b/i', $segment, $m)) return (float) $m[1];
        return null;
    }

    private function make(float $amount, string $currency, string $basis, ?float $quantity, string $raw): ParsedPrice
    {
        $ecto = match ($currency) {
            'a' => $amount * 27.0,
            'e' => $amount,
            'k' => $amount / 15.0,
            default => null,
        };
        $unit = $ecto;
        if ($ecto !== null && $quantity !== null && $quantity > 0 && !in_array($basis, ['each'], true)) {
            $unit = $ecto / $quantity;
        }
        return new ParsedPrice($amount, $currency, $ecto, $basis, $quantity, $unit, $raw);
    }

    private function number(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
