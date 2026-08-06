<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

/**
 * Parses item-for-item trades, e.g.:
 *   WTT Tengu flare 1:1 War supplies
 *   WTT Tengu, Guard or Conset 1:1 Ghastly
 */
final class ExchangeMatcher
{
    /**
     * @return array{
     *   left:string,right:string,give_quantity:float,receive_quantity:float,raw_ratio:string
     * }|null
     */
    public function parse(string $segment): ?array
    {
        if (!preg_match(
            '/^(.*?)\s+(\d+(?:[.,]\d+)?)\s*(?::|=|for)\s*(\d+(?:[.,]\d+)?)\s+(.+)$/iu',
            trim($segment),
            $match
        )) {
            return null;
        }

        $left = $this->cleanSide($match[1]);
        $right = $this->cleanSide($match[4]);

        if ($left === '' || $right === '') {
            return null;
        }

        return [
            'left' => $left,
            'right' => $right,
            'give_quantity' => $this->number($match[2]),
            'receive_quantity' => $this->number($match[3]),
            'raw_ratio' => $match[2] . ':' . $match[3],
        ];
    }

    public function normalizeFallbackName(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $lower = mb_strtolower($value);

        $aliases = [
            'ghastly' => 'Ghastly Summoning Stone',
            'ghastly stone' => 'Ghastly Summoning Stone',
            'ghastly stones' => 'Ghastly Summoning Stone',
            'war supply' => 'War Supplies',
            'war supplies' => 'War Supplies',
            'tengu flare' => 'Tengu Support Flare',
            'tengu support flare' => 'Tengu Support Flare',
            'guard' => 'Imperial Guard Reinforcement Order',
            'guard order' => 'Imperial Guard Reinforcement Order',
            'imperial guard' => 'Imperial Guard Reinforcement Order',
            'conset' => 'Conset',
            'consets' => 'Conset',
            'cons' => 'Conset',
        ];

        return $aliases[$lower] ?? $value;
    }

    /** @return list<string> */
    public function splitFallbackSources(string $value): array
    {
        $trimmed = trim($value);
        if (preg_match('/^(?:(?:tengu|guard|cons(?:et)?)\s*)+$/iu', $trimmed)) {
            preg_match_all('/tengu|guard|cons(?:et)?/iu', $trimmed, $found);
            $parts = $found[0] ?? [];
        } else {
            $parts = preg_split('/\s*(?:,|\/|\+|\bor\b|\band\b)\s*/iu', $trimmed) ?: [];
        }
        $parts = array_values(array_filter(array_map(
            fn(string $part): string => $this->normalizeFallbackName($part),
            $parts
        ), static fn(string $part): bool => $part !== ''));

        return $parts !== [] ? $parts : [$this->normalizeFallbackName($value)];
    }

    private function cleanSide(string $value): string
    {
        $value = preg_replace('/^\s*(?:wtt|trade|trading)\b\s*/iu', '', $value) ?? $value;
        $value = preg_replace('/\s*\b(?:pm|wsp|offer|offers)\b.*$/iu', '', $value) ?? $value;
        return trim($value, " \t\n\r\0\x0B-:;,/|<>+=");
    }

    private function number(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
