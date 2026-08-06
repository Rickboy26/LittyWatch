<?php
declare(strict_types=1);
namespace LittyWatch\Parser;

final class SmartSegmenter
{
    /** @return list<string> */
    public function split(string $text): array
    {
        $strong = preg_split('/\s*(?:\|+|;|\^+)\s*/u', trim($text)) ?: [$text];
        $out = [];
        foreach ($strong as $part) {
            if (trim($part)==='') continue;
            $plus = preg_split('/\s+\+\s+(?!\d)/u', trim($part)) ?: [$part];
            foreach ($plus as $piece) {
                // Comma + a word can be a new item. Comma + number/+modifier stays context.
                $comma = preg_split('/\s*,\s*(?=(?:unded|ded|el\s|elite\s|[A-Za-z][A-Za-z\'’ -]{2,})(?!\s*(?:energy|health|armor)\b))/iu', trim($piece)) ?: [$piece];
                foreach ($comma as $segment) {
                    $segment = trim($segment, " \t\n\r\0\x0B|;,");
                    if ($segment !== '') $out[] = $segment;
                }
            }
        }
        return $out;
    }
}
