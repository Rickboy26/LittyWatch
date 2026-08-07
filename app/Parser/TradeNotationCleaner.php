<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

/**
 * Removes trade quantity/basis notation from fallback item names.
 *
 * It deliberately only strips notation at token boundaries or at the end of
 * a candidate name, so legitimate words containing "stack" are untouched.
 */
final class TradeNotationCleaner
{
    public function cleanItemCandidate(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);


        // Strip parser metadata from fallback names. These are captured
        // separately by MarketMetadataExtractor / ModifierMatcher.
        $value = preg_replace('/^\s*(?:q|r|rq|req(?:uirement)?)\s*([0-9]{1,2})(?:\s*[-\/]\s*([0-9]{1,2}))?\s+/iu', ' ', $value) ?? $value;
        $value = preg_replace('/^\s*(?:unid(?:entified)?|insc(?:r(?:ibable|iptable)?)?|os|old\s*school|unded(?:icated)?|ded(?:icated)?)\b[\s:.-]*/iu', ' ', $value) ?? $value;
        $value = preg_replace('/\s+\b(?:unid(?:entified)?|insc(?:r(?:ibable|iptable)?)?|os|old\s*school)\b\s*$/iu', ' ', $value) ?? $value;

        // Quantities/basis words must not become part of a catalog candidate.
        $value = preg_replace('/^\s*\d+\s+(?=[\p{L}])/u', '', $value) ?? $value;
        $value = preg_replace('/\s+\b(?:x\s*\d+|\d+\s*x|each|ea|for\s+all)\b\s*$/iu', '', $value) ?? $value;
        $value = preg_replace('/\s+\b(?:tell\s+me\s+your\s+price|pm(?:\s+me)?|wsp(?:\s+me)?|offer(?:s)?)\b.*$/iu', '', $value) ?? $value;

        // Trailing decorative punctuation after price removal.
        $value = preg_replace('/[!?.=_-]{2,}\s*$/u', '', $value) ?? $value;

        // Price basis markers that often remain after the numeric price itself
        // has already been removed from the candidate.
        $value = preg_replace(
            '/(?:\s*\/\s*(?:st|stk|stack|ea|each)\b)+/iu',
            ' ',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\s*\bper\s+(?:full\s+)?(?:st|stk|stack|each|item)\b.*$/iu',
            '',
            $value
        ) ?? $value;

        // Quantity notations are metadata, not part of the item name.
        $value = preg_replace('/\s*\[\s*x\s*\d+\s*\]\s*/iu', ' ', $value) ?? $value;
        $value = preg_replace('/\s*\bx\s*250\b\s*$/iu', '', $value) ?? $value;
        $value = preg_replace('/\s*\b250\s*x\b\s*$/iu', '', $value) ?? $value;
        $value = preg_replace('/\s*\(\s*250\s*\)\s*$/u', '', $value) ?? $value;

        // A bare suffix is also common: "Rez stk" / "Rez stack".
        $value = preg_replace('/\s+\b(?:st|stk|stack)\b\s*$/iu', '', $value) ?? $value;

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value, " \t\n\r\0\x0B-:;,/|<>+=");
    }
}
