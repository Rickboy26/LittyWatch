<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class OfferSplitter
{
    /** @return list<array{trade_type:string,text:string}> */
    public function split(string $message): array
    {
        $message = trim($message);
        if ($message === '') return [];

        preg_match_all(
            '/\b(WTB|WTS|WTT|BUYING|SELLING|TRADING)\b/iu',
            $message,
            $markers,
            PREG_OFFSET_CAPTURE
        );

        if (!$markers[0]) {
            return [[
                'trade_type' => $this->detectType($message) ?? 'unknown',
                'text' => $message,
            ]];
        }

        $blocks = [];
        foreach ($markers[0] as $index => [$rawMarker, $offset]) {
            $start = $offset + strlen($rawMarker);
            $end = $markers[0][$index + 1][1] ?? strlen($message);
            $text = trim(
                substr($message, $start, $end - $start),
                " \t\n\r\0\x0B_|-/:"
            );

            if ($text === '') continue;

            $blocks[] = [
                'trade_type' => $this->markerType($rawMarker),
                'text' => $text,
            ];
        }

        return $blocks;
    }

    public function detectType(string $text): ?string
    {
        if (preg_match('/(?:^|\W)wtb(?:\W|$)|\bbuying\b/iu', $text)) return 'buy';
        if (preg_match('/(?:^|\W)wts(?:\W|$)|\bselling\b/iu', $text)) return 'sell';
        if (preg_match('/(?:^|\W)wtt(?:\W|$)|\btrading\b/iu', $text)) return 'trade';
        return null;
    }

    private function markerType(string $marker): string
    {
        return match (strtoupper($marker)) {
            'WTB', 'BUYING' => 'buy',
            'WTS', 'SELLING' => 'sell',
            'WTT', 'TRADING' => 'trade',
            default => 'unknown',
        };
    }
}
