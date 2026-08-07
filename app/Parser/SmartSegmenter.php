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

        if (preg_match('/\b(?:(elite|normal|regular|reg)\s+)?tomes?\s*:?[ ]*(.+)$/iu', $text, $match)) {
            $defaultKind = mb_strtolower(trim((string)($match[1] ?? '')));
            $tail = trim((string)$match[2]);
            $sharedPrice = null;
            if (preg_match('/\b(\d+(?:[.,]\d+)?)\s*(e|a|k|g)\s*(?:\/\s*ea|each)\b/iu', $tail, $pm)) {
                $sharedPrice = $pm[1] . $pm[2];
                $tail = trim(str_replace($pm[0], '', $tail));
            }

            $professionMap = [
                'w'=>'Warrior','war'=>'Warrior','warr'=>'Warrior','warrior'=>'Warrior',
                'r'=>'Ranger','rang'=>'Ranger','ranger'=>'Ranger',
                'mo'=>'Monk','monk'=>'Monk',
                'n'=>'Necromancer','necro'=>'Necromancer','necromancer'=>'Necromancer',
                'me'=>'Mesmer','mes'=>'Mesmer','mesmer'=>'Mesmer',
                'e'=>'Elementalist','ele'=>'Elementalist','elementalist'=>'Elementalist',
                'a'=>'Assassin','sin'=>'Assassin','assa'=>'Assassin','assassin'=>'Assassin',
                'rt'=>'Ritualist','rit'=>'Ritualist','ritualist'=>'Ritualist',
                'p'=>'Paragon','para'=>'Paragon','paragon'=>'Paragon',
                'd'=>'Dervish','derv'=>'Dervish','dervish'=>'Dervish',
            ];

            $entries = preg_split('/\s*(?:,|\/|\||;|\^|\+|\band\b|\bor\b|\s{2,})\s*/iu', $tail) ?: [];
            // Space-separated single-letter shorthand: "tomes P Mo N R A".
            if (count($entries) === 1 && preg_match('/^(?:[A-Za-z]{1,3}\s+){1,}[A-Za-z]{1,3}$/u', trim($tail))) {
                $entries = preg_split('/\s+/u', trim($tail)) ?: [];
            }

            $result = [];
            foreach ($entries as $entry) {
                $entry = trim($entry);
                if ($entry === '') continue;
                if (!preg_match('/^(?:(\d+)\s*x?\s*)?(elite\s+)?([A-Za-z]+)\b/iu', $entry, $parts)) continue;
                $qty = isset($parts[1]) && $parts[1] !== '' ? (int)$parts[1] : null;
                $elite = !empty($parts[2]) || $defaultKind === 'elite';
                $token = mb_strtolower($parts[3]);
                $profession = $professionMap[$token] ?? null;
                if ($profession === null) continue;
                $segment = ($qty !== null ? $qty . 'x ' : '') . ($elite ? 'Elite ' : '') . $profession . ' Tome';
                if ($sharedPrice !== null) $segment .= ' ' . $sharedPrice . ' each';
                $result[] = $segment;
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
