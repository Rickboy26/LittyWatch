<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class PriceMatcher
{
    public function parse(string $segment): ParsedPrice
    {
        // Ratio shorthand: 5:1e means five units for one ecto total.
        if (preg_match('/(?<!\d)(\d+(?:[.,]\d+)?)\s*:\s*(\d+(?:[.,]\d+)?)\s*(a|e|k)\b/i', $segment, $m)) {
            $quantity = $this->number($m[1]);
            $amount = $this->number($m[2]);
            return $this->make($amount, strtolower($m[3]), 'ratio', $quantity, $m[0]);
        }

        // Explicit quantity-for-total: "6 arms for 162e".
        if (preg_match('/(?<!\d)(\d+(?:[.,]\d+)?)\s+[^|;,]{1,45}?\bfor\s+(\d+(?:[.,]\d+)?)\s*(a|e|k)\b/i', $segment, $m)) {
            $quantity = $this->number($m[1]);
            $amount = $this->number($m[2]);
            return $this->make($amount, strtolower($m[3]), 'total', $quantity, $m[0]);
        }

        // Legacy "5=1e" shorthand, not currency conversion such as 1750e=64a.
        if (preg_match('/(?<![a-z0-9])(\d+(?:[.,]\d+)?)\s*=\s*(\d+(?:[.,]\d+)?)\s*(a|e|k)\b/i', $segment, $m)) {
            $quantity = $this->number($m[1]);
            $amount = $this->number($m[2]);
            return $this->make($amount, strtolower($m[3]), 'exchange', $quantity, $m[0]);
        }

        // Inspect every money token and prefer an explicit per-unit observation.
        // This prevents a later conversion ("1750e = 64a") from replacing an
        // earlier unit price ("27e/ea").
        preg_match_all(
            '/(?<![a-z0-9])(\d+(?:[.,]\d+)?)\s*(a|ambr(?:ace)?s?|armbraces?|e|ectos?|k|plat(?:inum)?)(?=\b|\/|$)/i',
            $segment,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $candidates = [];
        foreach ($matches[0] ?? [] as $i => $full) {
            $raw = (string)$full[0];
            $offset = (int)$full[1];
            $amount = $this->number((string)$matches[1][$i][0]);
            $currencyRaw = mb_strtolower((string)$matches[2][$i][0]);
            $currency = str_starts_with($currencyRaw, 'a') ? 'a' : (str_starts_with($currencyRaw, 'e') ? 'e' : 'k');
            $tailOffset = $offset + strlen($raw);
            $tail = substr($segment, $tailOffset, 32);
            $head = substr($segment, max(0, $offset - 18), min(18, $offset));

            if ($this->isCurrencyConversionToken($segment, $offset, strlen($raw))) {
                $candidates[] = ['priority'=>0,'price'=>$this->make($amount,$currency,'currency_conversion',null,$raw)];
                continue;
            }

            $basis = 'unqualified';
            $quantity = null;
            $priority = 1;

            if (preg_match('/^\s*\/\s*(?:st|stk|stack)\b/i', $tail)) {
                $basis = 'stack'; $quantity = 250.0; $priority = 5;
            } elseif (preg_match('/^\s*\/\s*(?:ea|each|e)\b/i', $tail) || preg_match('/^\s*(?:ea|each)\b/i', $tail)) {
                $basis = 'each'; $quantity = $this->detectInventoryQuantity($segment); $priority = 6;
            } elseif (preg_match('/^\s*(?:\/\s*)?x\s*(\d+)\b/i', $tail, $qm)) {
                // Kamadan convention: "27e x6" = six units at 27e each.
                $basis = 'each'; $quantity = (float)$qm[1]; $priority = 5;
            } elseif (preg_match('/\bper\s+(?:ea|each|unit|piece)\s*$/i', $head)) {
                $basis = 'each'; $quantity = $this->detectInventoryQuantity($segment); $priority = 6;
            }

            $candidates[] = ['priority'=>$priority,'price'=>$this->make($amount,$currency,$basis,$quantity,$raw)];
        }

        if ($candidates !== []) {
            usort($candidates, static fn(array $a,array $b): int => $b['priority'] <=> $a['priority']);
            return $candidates[0]['price'];
        }

        return new ParsedPrice(null, null, null, 'unknown');
    }

    private function isCurrencyConversionToken(string $segment, int $offset, int $length): bool
    {
        $before = substr($segment, max(0, $offset - 24), min(24, $offset));
        $after = substr($segment, $offset + $length, 24);
        // token is on either side of "27e = 1a" / "1a = 27e"
        return (bool)(
            preg_match('/=\s*$/', $before)
            || preg_match('/^\s*=\s*\d+(?:[.,]\d+)?\s*(?:a|e|k)\b/i', $after)
        );
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
        $unit = null;
        if ($ecto !== null) {
            if (in_array($basis, ['each','unqualified'], true)) {
                $unit = $ecto;
            } elseif ($quantity !== null && $quantity > 0 && in_array($basis, ['ratio','exchange','total','stack','set'], true)) {
                $unit = $ecto / $quantity;
            }
        }
        return new ParsedPrice($amount, $currency, $ecto, $basis, $quantity, $unit, $raw);
    }

    private function number(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
