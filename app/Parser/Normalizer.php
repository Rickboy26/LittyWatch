<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class Normalizer
{
    public function normalize(string $message): string
    {
        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = str_replace(["\r\n", "\r", "\t", '_'], [' ', ' ', ' ', ' '], $message);
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
