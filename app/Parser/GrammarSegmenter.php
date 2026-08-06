<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class GrammarSegmenter
{
    /** @return list<string> */
    public function split(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];

        // Protect common stat syntax before treating ^ as a separator.
        $text = preg_replace('/(\d)\s*\^\s*(\d)/u', '$1__CARET__$2', $text) ?? $text;

        $parts = preg_split(
            '/\s*(?:\|+|\/\/+|;|~+|\^(?!\d)|\s+-\s+)\s*/u',
            $text
        ) ?: [$text];

        $result = [];
        foreach ($parts as $part) {
            $part = str_replace('__CARET__', '^', trim($part));
            if ($part === '') continue;

            // "A & B" is normally two items, but not WTT ratio text.
            $ampersand = preg_split('/\s+&\s+/u', $part) ?: [$part];
            foreach ($ampersand as $piece) {
                $piece = trim($piece);
                if ($piece === '') continue;

                // Explicit counts followed by a second counted item.
                $counted = preg_split(
                    '/\s+(?=\d+\s+[A-Za-z][A-Za-z\'’ -]{2,}\b)/u',
                    $piece
                ) ?: [$piece];

                foreach ($counted as $entry) {
                    $entry = trim($entry, " \t\n\r\0\x0B|;,");
                    if ($entry !== '') $result[] = $entry;
                }
            }
        }

        return array_values($result);
    }
}
