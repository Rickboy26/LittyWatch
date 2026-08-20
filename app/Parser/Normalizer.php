<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class Normalizer
{
    public function normalize(string $message): string
    {
        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = str_replace(["\r\n", "\r", "\t", '_'], [' ', ' ', ' ', ' '], $message);
        // Normalize verbose trade intent before OfferSplitter looks for markers.
        $message = preg_replace('/\bwant\s+to\s+buy\b/iu', 'WTB', $message) ?? $message;
        $message = preg_replace('/\bwant\s+to\s+sell\b/iu', 'WTS', $message) ?? $message;

        // LITTYWATCH_PHASE8B3_TRADE_DIRECTION_RECOVERY
        // Recover unambiguous decorated/spaced Kamadan trade markers before
        // OfferSplitter searches for canonical WTB/WTS/WTT tokens.
        $message = preg_replace(
            '/(?<![a-z0-9])w[.\s-]*t[.\s-]*s(?![a-z0-9])/iu',
            'WTS',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/(?<![a-z0-9])w[.\s-]*t[.\s-]*b(?![a-z0-9])/iu',
            'WTB',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/(?<![a-z0-9])w[.\s-]*t[.\s-]*t(?!\s*-\s*b\b)(?![a-z0-9])/iu',
            'WTT',
            $message
        ) ?? $message;

        // Occasional single junk character directly before an otherwise
        // canonical WTS marker in collected Kamadan text.
        $message = preg_replace(
            '/(?<![a-z0-9])[wn]WTS\b/iu',
            'WTS',
            $message
        ) ?? $message;

        // LITTYWATCH_PHASE8C1_SELL_SHORTHAND
        // GW trade shorthand: "S:" at the start means selling.
        $message = preg_replace(
            '/^\s*S\s*:\s*/iu',
            'WTS ',
            $message
        ) ?? $message;

        // LITTYWATCH_PHASE8C2_PROVEN_WTS_TYPOS
        // Historical message pairs show TS/WRS immediately followed by the
        // same advertisement written as WTS. Restrict recovery to weapon-style
        // requirement syntax to avoid treating arbitrary TS/WRS text as sell.
        $message = preg_replace(
            '/^\s*(?:TS|WRS)\s+(?=(?:q|r|req)\s*[0-9]{1,2}\b)/iu',
            'WTS ',
            $message
        ) ?? $message;
        $message = preg_replace('/\^{2,}/u', ' | ', $message) ?? $message;
        $message = preg_replace('/(?<=\D)\^(?=\D)/u', ' ', $message) ?? $message;
        // Normalize spaced requirement notation before segmentation: "q 9" -> "q9".
        $message = preg_replace('/\b([qr])\s+([0-9]{1,2})\b/iu', '$1$2', $message) ?? $message;

        // Decorative Kamadan punctuation is presentation, not market data.
        // Keep meaningful single '-' stat markers intact, but collapse long runs.
        $message = preg_replace('/(?<!\d)[!?.*_=]{3,}/u', ' ', $message) ?? $message;
        $message = preg_replace('/-{3,}/u', ' ', $message) ?? $message;
        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;
        return trim($message, " \t\n\r\0\x0B|,;:!?.=_-");
    }
}
