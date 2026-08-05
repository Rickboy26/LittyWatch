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
        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;
        return trim($message);
    }
}
