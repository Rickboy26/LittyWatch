<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

/**
 * Expands compact market lists with one shared prefix/price:
 *   q9 insc 2e/ea: WingedAxe, DualWingedAxe, HaloAxe
 * into independent segments that each inherit q9/inscribable/2e each.
 */
final class SharedOfferListExpander
{
    /** @return list<string>|null */
    public function expand(string $text): ?array
    {
        $text = trim($text);
        if (!str_contains($text, ':') || !str_contains($text, ',')) return null;

        if (!preg_match('/^(.*?)\s*:\s*(.+)$/u', $text, $m)) return null;
        $prefix = trim($m[1]);
        $tail = trim($m[2]);

        // Only expand when the prefix clearly carries market metadata rather
        // than being part of a normal item name.
        if (!preg_match('/\b(?:q|r)\s*\d{1,2}\b|\binscribable\b|\b\d+(?:[.,]\d+)?\s*(?:e|a|k)\s*\/\s*(?:ea|each)\b/iu', $prefix)) {
            return null;
        }

        $parts = preg_split('/\s*,\s*/u', $tail) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn(string $v): bool => $v !== ''));
        if (count($parts) < 2) return null;

        // Avoid splitting descriptive prose/stat lists.
        foreach ($parts as $part) {
            if (mb_strlen($part) > 70) return null;
        }

        $out = [];
        foreach ($parts as $part) {
            $out[] = trim($prefix . ' ' . $part);
        }
        return $out;
    }
}
