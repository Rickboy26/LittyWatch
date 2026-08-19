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

        // Phase 8A: a terminal explicit per-unit quote belongs to every member of
        // a comma/slash list unless that member already has its own money quote.
        // This is deliberately limited to `/ea`/`each`; a bare trailing amount is
        // still ambiguous and remains parser-review material.
        $sharedTrailingPrice = null;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(a|ambr(?:ace)?s?|armbraces?|e|ectos?|k|plat(?:inum)?)\s*(?:\/\s*)?(?:ea|each)\s*$/iu', $text, $shared)) {
            $sharedTrailingPrice = trim((string)$shared[0]);
        }

        $tomeMatch = null;
        if (preg_match('/\b(?:(elite|normal|regular|reg)\s+)?tomes?\s*:?[ ]*(.+)$/iu', $text, $match)) {
            $tomeMatch = $match;
        } elseif (preg_match('/^\s*(elite|normal|regular|reg)\s+(.+?(?:\(\s*\d+\s*\)|\bx\s*\d+).+?)\s+(\d+(?:[.,]\d+)?\s*(?:e|a|k|g)\s*(?:\/\s*ea|each))\s*$/iu', $text, $match)) {
            // Live shorthand may omit "Tome(s)": Elite Monk (12) Ele (6) Mes (10) 2e/ea.
            // Multiple inventory counts plus an explicit shared /ea price make
            // profession-tome intent sufficiently specific to expand safely.
            $match[2] = trim((string)$match[2].' '.(string)$match[3]);
            $tomeMatch = $match;
        }

        if ($tomeMatch !== null) {
            $match = $tomeMatch;
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

            // Parenthesized inventory notation is common in tome lists:
            // `Elite Monk (12) Ele (6) Mes (10) 2e/ea`. Preserve those counts
            // before the generic entry splitter discards the parentheses.
            $sequenceEntries = [];
            if (preg_match_all('/(?:(elite)\s+)?([A-Za-z]+)\s*(?:\(\s*(\d+)\s*\)|x\s*(\d+))?/iu', $tail, $seq, PREG_SET_ORDER)) {
                foreach ($seq as $part) {
                    $token = mb_strtolower((string)($part[2] ?? ''));
                    if (!isset($professionMap[$token])) { $sequenceEntries = []; break; }
                    $qty = (string)($part[3] ?? '') !== '' ? (int)$part[3] : ((string)($part[4] ?? '') !== '' ? (int)$part[4] : null);
                    $sequenceEntries[] = ['token'=>$token,'qty'=>$qty,'elite'=>!empty($part[1])];
                }
            }

            $entries = preg_split('/\s*(?:,|\/|\||;|\^|\+|\band\b|\bor\b|\s{2,})\s*/iu', $tail) ?: [];
            // Space-separated single-letter shorthand: "tomes P Mo N R A".
            if (count($entries) === 1 && preg_match('/^(?:[A-Za-z]{1,3}\s+){1,}[A-Za-z]{1,3}$/u', trim($tail))) {
                $entries = preg_split('/\s+/u', trim($tail)) ?: [];
            }

            $result = [];
            if (count($sequenceEntries) > 1) {
                foreach ($sequenceEntries as $entry) {
                    $profession = $professionMap[$entry['token']];
                    $elite = $entry['elite'] || $defaultKind === 'elite';
                    $segment = ($entry['qty'] !== null ? $entry['qty'].'x ' : '') . ($elite ? 'Elite ' : '') . $profession . ' Tome';
                    if ($sharedPrice !== null) $segment .= ' ' . $sharedPrice . ' each';
                    $result[] = $segment;
                }
                return $result;
            }
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

        // Explicit shared trailing `/ea` / `each` makes a comma list safe to
        // distribute before the normal name-oriented comma heuristic runs.
        // Existing per-item prices are preserved and are never overwritten.
        if ($sharedTrailingPrice !== null && str_contains($text, ',')) {
            $base = preg_replace('/'.preg_quote($sharedTrailingPrice, '/').'\s*$/iu', '', $text, 1) ?? $text;
            $parts = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/u', $base) ?: []), static fn(string $v): bool => $v !== ''));
            if (count($parts) > 1) {
                foreach ($parts as $i => $part) {
                    if (!preg_match('/(?<![a-z0-9])\d+(?:[.,]\d+)?\s*(?:a|ambr(?:ace)?s?|armbraces?|e|ectos?|k|plat(?:inum)?)(?=\b|\/|$)/iu', $part)) {
                        $parts[$i] = trim($part.' '.$sharedTrailingPrice);
                    }
                }
                return $parts;
            }
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

        if ($sharedTrailingPrice !== null && count($out) > 1) {
            foreach ($out as $i => $segment) {
                if (!preg_match('/(?<![a-z0-9])\d+(?:[.,]\d+)?\s*(?:a|ambr(?:ace)?s?|armbraces?|e|ectos?|k|plat(?:inum)?)(?=\b|\/|$)/iu', $segment)) {
                    $out[$i] = trim($segment.' '.$sharedTrailingPrice);
                }
            }
        }

        return array_values($out);
    }
}
