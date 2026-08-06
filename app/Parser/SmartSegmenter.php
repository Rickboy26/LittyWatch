<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class SmartSegmenter
{
    /** @return list<string> */
    public function split(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];

        if (preg_match('/\btomes?\s*:\s*(.+)$/iu', $text, $match)) {
            $entries = preg_split(
                '/\s*(?:,|\/|\||;|\^|\+|\band\b|\bor\b)\s*/iu',
                $match[1]
            ) ?: [];

            $result = [];
            foreach ($entries as $entry) {
                $entry = trim($entry);
                if ($entry === '') continue;

                if (preg_match(
                    '/^(elite\s+)?([a-z]+)(?:\s*\([^)]*\))?(.*)$/iu',
                    $entry,
                    $parts
                )) {
                    $elite = !empty($parts[1]) ? 'Elite ' : '';
                    $profession = trim($parts[2]);
                    $suffix = trim($parts[3] ?? '');
                    $result[] = trim($elite . $profession . ' Tome ' . $suffix);
                }
            }

            if ($result !== []) return $result;
        }

        $strong = preg_split('/\s*(?:\|+|;|\^+)\s*/u', $text) ?: [$text];
        $out = [];

        foreach ($strong as $part) {
            $part = trim($part);
            if ($part === '') continue;

            $plus = preg_split('/\s+\+\s+(?!\d)/u', $part) ?: [$part];

            foreach ($plus as $piece) {
                $piece = trim($piece);
                if ($piece === '') continue;

                $comma = preg_split(
                    '/\s*,\s*(?=(?:unded|ded|el\s|elite\s|[A-Za-z][A-Za-z\'’ -]{2,})(?!\s*(?:energy|health|armor|soul\s+reaping|channeling|communing|domination|inspiration)\b))/iu',
                    $piece
                ) ?: [$piece];

                foreach ($comma as $segment) {
                    $segment = trim($segment, " \t\n\r\0\x0B|;,");
                    if ($segment !== '') $out[] = $segment;
                }
            }
        }

        return array_values($out);
    }
}
