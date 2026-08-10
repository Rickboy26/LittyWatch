<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

/**
 * Phase 4D market-list expander.
 *
 * It only expands lists whose identity can be reconstructed with high
 * confidence. Generic weapon families deliberately remain unresolved.
 */
final class MarketBundleExpander
{
    private const PROFESSIONS = [
        'war'=>'Warrior','warr'=>'Warrior','warrior'=>'Warrior',
        'rang'=>'Ranger','ranger'=>'Ranger',
        'mo'=>'Monk','monk'=>'Monk',
        'nec'=>'Necromancer','necro'=>'Necromancer','necromancer'=>'Necromancer',
        'mes'=>'Mesmer','mesmer'=>'Mesmer',
        'ele'=>'Elementalist','elementalist'=>'Elementalist',
        'sin'=>'Assassin','assassin'=>'Assassin',
        'rit'=>'Ritualist','ritualist'=>'Ritualist',
        'para'=>'Paragon','paragon'=>'Paragon',
        'derv'=>'Dervish','dervish'=>'Dervish',
    ];

    private const MINIATURES = [
        'ghostly hero'=>'Miniature Ghostly Hero',
        'undead prince'=>'Miniature Undead Prince',
        'undead prince rurik'=>'Miniature Undead Prince',
        'prince rurik'=>'Miniature Prince Rurik',
        'kuuna'=>'Miniature Kuunavang',
        'kuunavang'=>'Miniature Kuunavang',
        'kuunaavang'=>'Miniature Kuunavang',
        'zhed'=>'Miniature Zhed Shadowhoof',
        'zhed shadowhoof'=>'Miniature Zhed Shadowhoof',
        'rift warden'=>'Miniature Rift Warden',
        'xun rao'=>'Miniature Ecclesiate Xun Rao',
        'preacher xun rao'=>'Miniature Ecclesiate Xun Rao',
        'ecclesiate xun rao'=>'Miniature Ecclesiate Xun Rao',
        'dagnar'=>'Miniature Dagnar Stonepate',
        'dagnar stonepate'=>'Miniature Dagnar Stonepate',
        'lich'=>'Miniature Lich',
        'naga'=>'Miniature Naga',
        'oni'=>'Miniature Oni',
        "shiro'ken assassin"=>"Miniature Shiro'ken Assassin",
        'shiroken assassin'=>"Miniature Shiro'ken Assassin",
        'vizu'=>'Miniature Vizu',
        'shiro'=>'Miniature Shiro',
        'water djinn'=>'Miniature Water Djinn',
        'zhu hanuku'=>'Miniature Zhu Hanuku',
        'black beast'=>'Miniature Black Beast of Aaaaarrrrrrggghhh',
        'black beast of aaaaarrrrrrggghhh'=>'Miniature Black Beast of Aaaaarrrrrrggghhh',
        'king adelbern'=>'Miniature King Adelbern',
        'destroyer'=>'Miniature Destroyer of Flesh',
        'destroyer of flesh'=>'Miniature Destroyer of Flesh',
        'varesh'=>'Miniature Varesh',
        'varesh ossa'=>'Miniature Varesh',
        'madruk dhuum'=>'Miniature Madruk Dhuum',
        'forest griffon'=>'Miniature Forest Griffon',
    ];

    /** @return list<string>|null */
    public function expand(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') return null;

        foreach ([
            'expandRepeatedPointList',
            'expandCompactPointBundle',
            'expandMiniatureList',
            'expandGhostlyStaffAttributeList',
            'expandProfessionTomeBundle',
        ] as $method) {
            $result = $this->{$method}($text);
            if ($result !== null) return $result;
        }

        $materials = $this->expandPairBundle(
            $text,
            '/\biron\s*(?:\/|&|and)\s*dust\b/iu',
            ['Iron Ingot', 'Pile of Glittering Dust']
        );
        if ($materials !== null) return $materials;

        $doa = $this->expandPairBundle(
            $text,
            '/\bpowerstones?\s*(?:\/|&|and)\s*stygian\s+(?:gems?|gemstones?)\b/iu',
            ['Powerstone of Courage', 'Stygian Gemstone']
        );
        if ($doa !== null) return $doa;

        return null;
    }

    /** @return list<string>|null */
    private function expandRepeatedPointList(string $text): ?array
    {
        if (!preg_match_all(
            '/(?<![\p{L}\p{N}])(\d{1,3}(?:[.,]\d{3})+|\d+)\s*(party|sweet|alc(?:ohol)?)(?:\s+points?)?/iu',
            $text,
            $m,
            PREG_SET_ORDER
        ) || count($m) < 2) {
            return null;
        }

        $map = ['party'=>'Party Points','sweet'=>'Sweet Points','alc'=>'Alcohol Points','alcohol'=>'Alcohol Points'];
        $out = [];
        foreach ($m as $row) {
            $qty = preg_replace('/[.,](?=\d{3}(?:\D|$))/u', '', $row[1]) ?? $row[1];
            $key = mb_strtolower($row[2]);
            if (!isset($map[$key])) continue;
            $out[] = trim($qty . ' ' . $map[$key]);
        }
        return count($out) >= 2 ? array_values(array_unique($out)) : null;
    }

    /** @return list<string>|null */
    private function expandCompactPointBundle(string $text): ?array
    {
        if (!preg_match(
            '/\b((?:party|sweet|alc(?:ohol)?)(?:\s*\/\s*(?:party|sweet|alc(?:ohol)?)){1,2})\b(.*)$/iu',
            $text,
            $m,
            PREG_OFFSET_CAPTURE
        )) return null;

        $map = ['party'=>'Party Points','sweet'=>'Sweet Points','alc'=>'Alcohol Points','alcohol'=>'Alcohol Points'];
        $bundle = $m[1][0];
        $offset = $m[1][1];
        $before = trim(substr($text, 0, $offset));
        $after = trim($m[2][0]);

        $items = [];
        foreach (preg_split('/\s*\/\s*/u', $bundle) ?: [] as $token) {
            $key = mb_strtolower(trim($token));
            if (!isset($map[$key])) return null;
            $items[$map[$key]] = true;
        }
        return count($items) >= 2 ? $this->withSharedContext(array_keys($items), $before, $after) : null;
    }

    /** @return list<string>|null */
    private function expandMiniatureList(string $text): ?array
    {
        $state = null;
        $body = null;

        if (preg_match(
            '/^(?:(?:gold|green|purple|white)\s+)?(?:(unded(?:icated)?|ded(?:icated)?)\s+)?(?:miniatures?|minis?|minipets?)\s*[:\-]?\s*(.+)$/iu',
            $text,
            $m
        )) {
            $state = $this->state($m[1] ?? '');
            $body = trim($m[2]);
        } elseif (preg_match(
            '/^(?:(?:gold|green|purple|white)\s+)?(?:(unded(?:icated)?|ded(?:icated)?)\s+)?mini\s+(.+)$/iu',
            $text,
            $m
        )) {
            $state = $this->state($m[1] ?? '');
            $body = trim($m[2]);
        }

        // LITTYWATCH_PHASE4E_SHARED_MINI_STATE
        if ($body === null && preg_match('/^(?:wts|wtb|wtt)?\s*(unded(?:icated)?|ded(?:icated)?)\s+(.+[,\/].+)$/iu',trim($text),$sm)) {
            $state = $this->state($sm[1]);
            $body = trim($sm[2]);
        }

        // No explicit "mini" header: accept slash/comma lists only if every
        // member is a known miniature shorthand.
        $implicit = false;
        if ($body === null && preg_match('/[,\/]/u', $text)) {
            $body = $text;
            $implicit = true;
        }
        if ($body === null || $body === '') return null;

        // A single trailing package price belongs to the whole list, not each
        // miniature. Remove it before splitting unless it explicitly says each.
        $body = preg_replace(
            '/\s+\d+(?:[.,]\d+)?\s*(?:a|e|k)\s*(?:obo)?\s*$/iu',
            '',
            $body
        ) ?? $body;

        $rawParts = array_values(array_filter(array_map(
            'trim',
            preg_split('/\s*(?:,|\/)\s*/u', $body) ?: []
        )));
        if ($rawParts === []) return null;

        $out = [];
        foreach ($rawParts as $raw) {
            $localState = $state;
            if (preg_match('/^(unded(?:icated)?|ded(?:icated)?)\s+/iu', $raw, $sm)) {
                $localState = $this->state($sm[1]);
                $raw = trim(preg_replace('/^(?:unded(?:icated)?|ded(?:icated)?)\s+/iu', '', $raw, 1) ?? $raw);
            }
            $raw = trim(preg_replace('/\s+(?:mini|miniature|minipet)s?\s*$/iu', '', $raw) ?? $raw);
            $raw = trim(preg_replace('/^(?:mini|miniature|minipet)s?\s+/iu', '', $raw) ?? $raw);
            $key = $this->miniKey($raw);
            if (!isset(self::MINIATURES[$key])) {
                if ($implicit) return null;
                continue;
            }
            $candidate = self::MINIATURES[$key];
            if ($localState !== null) $candidate .= ' ' . $localState;
            $out[] = $candidate;
        }

        if ($implicit && count($out) < 2) return null;
        return $out !== [] ? array_values(array_unique($out)) : null;
    }

    /** @return list<string>|null */
    private function expandGhostlyStaffAttributeList(string $text): ?array
    {
        if (!preg_match('/^ghostly\s+staffs?\s+(.+)$/iu', trim($text), $m)) return null;

        $tail = trim($m[1]);
        $attrMap = [
            'divine'=>'Divine Favor','df'=>'Divine Favor',
            'channel'=>'Channeling Magic','chan'=>'Channeling Magic','channeling'=>'Channeling Magic',
            'death'=>'Death Magic',
            'earth'=>'Earth Magic',
            'curses'=>'Curses','curs'=>'Curses',
            'dom'=>'Domination Magic',
            'air'=>'Air Magic','water'=>'Water Magic','fire'=>'Fire Magic',
            'blood'=>'Blood Magic','sr'=>'Soul Reaping',
            'fc'=>'Fast Casting','es'=>'Energy Storage',
            'heal'=>'Healing Prayers','prot'=>'Protection Prayers',
            'resto'=>'Restoration Magic','comm'=>'Communing',
        ];

        if (!preg_match_all(
            '/\b([A-Za-z]+)\s+q\s*(\d{1,2}(?:\s*,\s*(?:q\s*)?\d{1,2})*)/iu',
            $tail,
            $groups,
            PREG_SET_ORDER
        )) return null;

        $out = [];
        foreach ($groups as $g) {
            $attrKey = mb_strtolower($g[1]);
            if (!isset($attrMap[$attrKey])) continue;
            preg_match_all('/\d{1,2}/u', $g[2], $qs);
            foreach ($qs[0] ?? [] as $q) {
                $out[] = 'Ghostly Staff q' . $q . ' ' . $attrMap[$attrKey];
            }
        }
        return count($out) >= 2 ? array_values(array_unique($out)) : null;
    }

    /** @return list<string>|null */
    private function expandProfessionTomeBundle(string $text): ?array
    {
        if (!preg_match(
            '/\b(?:(\d+)\s+)?(elite|normal|regular)\s+tomes?\s*\(([^)]+)\)/iu',
            $text,
            $m
        )) return null;

        $kind = mb_strtolower($m[2]);
        $inside = trim($m[3]);
        $perProfessionQuantity = null;

        if (preg_match('/^\s*(\d+)\s*x\s*/iu', $inside, $qm)) {
            $perProfessionQuantity = (int)$qm[1];
            $inside = preg_replace('/^\s*\d+\s*x\s*/iu', '', $inside, 1) ?? $inside;
        }

        $professions = [];
        foreach (preg_split('/\s*(?:,|\/|&|\band\b)\s*/iu', $inside) ?: [] as $token) {
            $token = trim(preg_replace('/^\d+\s*x\s*/iu', '', $token) ?? $token);
            $key = mb_strtolower($token);
            if (isset(self::PROFESSIONS[$key])) $professions[self::PROFESSIONS[$key]] = true;
        }
        if ($professions === []) return null;

        $out = [];
        foreach (array_keys($professions) as $profession) {
            $item = $kind === 'elite' ? 'Elite '.$profession.' Tome' : $profession.' Tome';
            if ($perProfessionQuantity !== null && $perProfessionQuantity > 0) {
                $item = $perProfessionQuantity.' '.$item;
            }
            $out[] = $item;
        }
        return $out;
    }

    /** @param list<string> $items @return list<string>|null */
    private function expandPairBundle(string $text, string $pattern, array $items): ?array
    {
        if (!preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) return null;
        $offset = $m[0][1];
        $length = strlen($m[0][0]);
        $before = trim(substr($text, 0, $offset));
        $after = trim(substr($text, $offset + $length));
        return $this->withSharedContext($items, $before, $after);
    }

    /** @param list<string> $items @return list<string> */
    private function withSharedContext(array $items, string $before, string $after): array
    {
        if ($before !== '' && !preg_match('/^(?:\d+(?:[.,]\d+)?\s*)?$/u', $before)) $before = '';
        $out = [];
        foreach ($items as $item) {
            $out[] = trim(($before !== '' ? $before.' ' : '').$item.($after !== '' ? ' '.$after : ''));
        }
        return $out;
    }

    private function state(string $raw): ?string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') return null;
        return str_starts_with($raw, 'un') ? 'unded' : 'ded';
    }

    private function miniKey(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        $raw = strtr($raw, ['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
        $raw = preg_replace('/\s+/u', ' ', $raw) ?? $raw;
        return trim($raw, " \t\n\r\0\x0B:;-");
    }
}