<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

use LittyWatch\Knowledge\KnowledgeBase;
use PDO;

/**
 * Phase 4D market-list expander.
 *
 * It only expands lists whose identity can be reconstructed with high
 * confidence. Generic weapon families deliberately remain unresolved.
 */
final class MarketBundleExpander
{
    public function __construct(private readonly ?PDO $pdo = null) {}

    private const PROFESSIONS = [
        // LITTYWATCH_PHASE8E_ELITE_TOME_CODES
        // Proven compact Elite Tome profession codes.
        'n'=>'Necromancer',
        'r'=>'Ranger',
        'm'=>'Monk',
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
        'asu'=>'Miniature Asura',
        'asura'=>'Miniature Asura',
        'madruk dhuum'=>'Miniature Madruk Dhuum',
        'forest griffon'=>'Miniature Forest Griffon',
        'shaman'=>'Miniature Charr Shaman',
        'roaringether'=>'Miniature Roaring Ether',
        'wdjinn'=>'Miniature Water Djinn',
        'arghhh'=>'Miniature Black Beast of Aaaaarrrrrrggghhh',
        'fdjin'=>'Miniature Flame Djinn',
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
            // LITTYWATCH_PHASE8G_CONCRETE_WEAPON_LISTS
            'expandEternalBowList',
            'expandEternalShieldList',
            'expandFellbladeRequirementContinuation',
            'expandOldschoolStaffMatrix',
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

        // LITTYWATCH_PHASE8D2C6_MINIPET_SHARED_PRICE_HEADER
        // "unded Minipet 3k/ea : Shaman, Nornbear, ..."
        // The price belongs to the shared list header, not to a miniature name.
        if ($body === null && preg_match(
            '/^(unded(?:icated)?|ded(?:icated)?)\s+(?:miniatures?|minis?|minipets?|mini\s*pets?)\s+\d+(?:[.,]\d+)?\s*(?:a|e|k)\s*\/\s*(?:ea|each)\s*:\s*(.+)$/iu',
            trim($text),
            $hm
        )) {
            $state = $this->state($hm[1]);
            $body = trim($hm[2]);
        }

        // LITTYWATCH_PHASE8D2C5_MINI_STATE_PREFIX
        // "Mini unded A, B, C" / "Mini ded A, B, C":
        // dedication belongs to the complete miniature list.
        elseif (preg_match(
            '/^(?:(?:gold|green|purple|white)\s+)?mini\s+(unded(?:icated)?|ded(?:icated)?)\s+(.+)$/iu',
            $text,
            $m
        )) {
            $state = $this->state($m[1]);
            $body = trim($m[2]);
        } elseif (preg_match(
            '/^(?:(?:gold|green|purple|white)\s+)?(?:(unded(?:icated)?|ded(?:icated)?)\s+)?(?:miniatures?|minis?|minipets?)\s*[:\-]?\s*(.+)$/iu',
            $text,
            $m
        )) {
            $state = $this->state($m[1] ?? '');
            $body = trim($m[2]);
        } elseif (preg_match(
            '/^(?:(?:gold|green|purple|white)\s+)?mini\s+(?:(unded(?:icated)?|ded(?:icated)?)\s+)?(.+)$/iu',
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
            preg_split('/\s*(?:,|\/|&|\band\b)\s*/iu', $body) ?: []
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

            // LITTYWATCH_PHASE8D2C2_MINI_LIST_LOCAL_PRICE
            // A list member may carry its own price:
            //   Ded Minis: Varesh - 5a, Asu
            // The price is market metadata, not part of the miniature identity.
            $raw = trim(preg_replace(
                '/\s*[-:=]?\s*\d+(?:[.,]\d+)?\s*(?:a|e|k)(?:\s*\/\s*(?:ea|each))?\s*$/iu',
                '',
                $raw
            ) ?? $raw);

            $key = $this->miniKey($raw);

            // LITTYWATCH_PHASE8D2C4_MINIATURE_CATALOG_RESOLUTION
            // Inside an explicit miniature list, resolve only against catalogue
            // entries in the miniature category. This prevents generic catalogue
            // identities such as Titan Gemstone from winning over a miniature.
            $candidate = $this->resolveMiniatureFromCatalog($raw);

            // Proven Kamadan shorthand remains a conservative fallback.
            if ($candidate === null && isset(self::MINIATURES[$key])) {
                $candidate = self::MINIATURES[$key];
            }

            if ($candidate === null) {
                if ($implicit) return null;
                continue;
            }

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

    /** LITTYWATCH_PHASE8G_ETERNAL_BOW_LIST
     * @return list<string>|null
     */
    private function expandEternalBowList(string $text): ?array
    {
        if (!preg_match('/^eternal\s+bows?\s*[:\-]\s*(.+)$/iu', trim($text), $m)) {
            return null;
        }

        $parts = array_values(array_filter(array_map(
            'trim',
            preg_split('/\s*,\s*/u', trim($m[1])) ?: []
        )));

        if (count($parts) < 2) return null;

        $types = [
            'flatbow'=>'Flatbow',
            'flat bow'=>'Flatbow',
            'longbow'=>'Longbow',
            'long bow'=>'Longbow',
            'shortbow'=>'Shortbow',
            'short bow'=>'Shortbow',
            'hornbow'=>'Hornbow',
            'horn bow'=>'Hornbow',
            'recurvebow'=>'Recurve Bow',
            'recurve bow'=>'Recurve Bow',
        ];

        $out=[];

        foreach($parts as $part){
            if(!preg_match(
                '/^(?:q|r)\s*(\d{1,2})\s+(.+?)(?:\s+(\d+(?:[.,]\d+)?\s*(?:a|e|k)))?$/iu',
                $part,
                $pm
            )){
                return null;
            }

            $type=mb_strtolower(trim($pm[2]));
            if(!isset($types[$type])) return null;

            $candidate='Eternal Bow q'.$pm[1].' '.$types[$type];

            if(!empty($pm[3])) $candidate.=' '.trim($pm[3]);

            $out[]=$candidate;
        }

        return count($out)>=2 ? array_values(array_unique($out)) : null;
    }


    /** LITTYWATCH_PHASE8G_ETERNAL_SHIELD_LIST
     * @return list<string>|null
     */
    private function expandEternalShieldList(string $text): ?array
    {
        if (!preg_match('/^eternal\s+shields?\s*[:\-]\s*(.+)$/iu', trim($text), $m)) {
            return null;
        }

        $parts = array_values(array_filter(array_map(
            'trim',
            preg_split('/\s*,\s*/u', trim($m[1])) ?: []
        )));

        if (count($parts) < 2) return null;

        $attrs=[
            'tact'=>'Tactics',
            'tac'=>'Tactics',
            'tactics'=>'Tactics',
            'comm'=>'Command',
            'command'=>'Command',
            'mot'=>'Motivation',
            'motivation'=>'Motivation',
            'str'=>'Strength',
            'strength'=>'Strength',
        ];

        $out=[];

        foreach($parts as $part){
            if(!preg_match(
                '/^(?:q|r)\s*(\d{1,2})\s+([A-Za-z]+)(?:\s+(\d+(?:[.,]\d+)?\s*(?:a|e|k)))?$/iu',
                $part,
                $pm
            )){
                return null;
            }

            $attr=mb_strtolower($pm[2]);
            if(!isset($attrs[$attr])) return null;

            $candidate='Eternal Shield q'.$pm[1].' '.$attrs[$attr];

            if(!empty($pm[3])) $candidate.=' '.trim($pm[3]);

            $out[]=$candidate;
        }

        return count($out)>=2 ? array_values(array_unique($out)) : null;
    }


    /** LITTYWATCH_PHASE8G_FELLBLADE_CONTINUATION
     * @return list<string>|null
     */
    private function expandFellbladeRequirementContinuation(string $text): ?array
    {
        if(!preg_match(
            '/^fellblade\s+((?:q|r)\s*\d{1,2}\b.*?)\s+-\s+((?:q|r)\s*\d{1,2}\b.*?)$/iu',
            trim($text),
            $m
        )){
            return null;
        }

        return [
            trim('Fellblade '.$m[1]),
            trim('Fellblade '.$m[2]),
        ];
    }


    /** LITTYWATCH_PHASE8G_OS_STAFF_MATRIX
     * @return list<string>|null
     */
    private function expandOldschoolStaffMatrix(string $text): ?array
    {
        if(!preg_match(
            '/^(?:os|old\s*school)\s+(?:q|r)\s*(\d{1,2})\s+(?:staves|staffs|staff)\s+(\d{1,2}\s*\/\s*\d{1,2})\s+(.+)$/iu',
            trim($text),
            $m
        )){
            return null;
        }

        $q=$m[1];
        $mods=preg_replace('/\s+/u','',$m[2]) ?? $m[2];

        $skins=[
            'dragon'=>'Dragon Staff',
            'bo'=>'Bo Staff',
            'ghost'=>'Ghostly Staff',
            'ghostly'=>'Ghostly Staff',
            'outcast'=>'Outcast Staff',
            'plag'=>'Plagueborn Staff',
            'plague'=>'Plagueborn Staff',
            'plagueborn'=>'Plagueborn Staff',
            'jade'=>'Jade Staff',
        ];

        $attrs=[
            'channeling'=>'Channeling Magic',
            'channel'=>'Channeling Magic',
            'chan'=>'Channeling Magic',
            'chann'=>'Channeling Magic',
            'smite'=>'Smiting Prayers',
            'smiting'=>'Smiting Prayers',
            'dom'=>'Domination Magic',
            'domination'=>'Domination Magic',
            'curs'=>'Curses',
            'curse'=>'Curses',
            'curses'=>'Curses',
            'sp'=>'Spawning Power',
            'spaw'=>'Spawning Power',
            'spawn'=>'Spawning Power',
            'spawning'=>'Spawning Power',
            'illus'=>'Illusion Magic',
            'illu'=>'Illusion Magic',
            'illusion'=>'Illusion Magic',
        ];

        $groups=array_values(array_filter(array_map(
            'trim',
            preg_split('/\s*~\s*/u', trim($m[3])) ?: []
        )));

        if(count($groups)<2) return null;

        $out=[];

        foreach($groups as $group){
            $tokens=array_values(array_filter(
                preg_split('/\s+/u',$group) ?: []
            ));

            if(count($tokens)<2) return null;

            $skinKey=mb_strtolower(array_shift($tokens));
            if(!isset($skins[$skinKey])) return null;

            foreach($tokens as $token){
                $attrKey=mb_strtolower($token);
                if(!isset($attrs[$attrKey])) return null;

                $out[]=
                    $skins[$skinKey]
                    .' q'.$q
                    .' '.$attrs[$attrKey]
                    .' '.$mods
                    .' OS';
            }
        }

        return count($out)>=2 ? array_values(array_unique($out)) : null;
    }


    /** @return list<string>|null */
    private function expandProfessionTomeBundle(string $text): ?array
    {
        // LITTYWATCH_PHASE8E2_PRICED_ELITE_TOME_LIST
        // "Elite Tomes 2e/ea (N, R, M)" -> "Elite Tomes (N, R, M)"
        // Price is shared market metadata; identity expansion only needs the list.
        $text = preg_replace(
            '/\b((?:elite|normal|regular)\s+tomes?)\s+\d+(?:[.,]\d+)?\s*(?:a|e|k)\s*(?:\/\s*(?:ea|each)|ea|each)?\s*(?=\()/iu',
            '$1 ',
            $text
        ) ?? $text;

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

    private function resolveMiniatureFromCatalog(string $raw): ?string
    {
        if ($this->pdo === null) return null;

        $raw = trim($raw);
        if ($raw === '') return null;

        $needle = KnowledgeBase::normalize($raw);
        if ($needle === '') return null;

        $matches = [];

        // 1. Exact canonical miniature name.
        $st = $this->pdo->prepare("
            SELECT DISTINCT key,name
            FROM kb_items
            WHERE active=1
              AND category_key='miniature'
              AND lower(trim(name))=lower(trim(:name))
        ");
        $st->execute([':name'=>$raw]);

        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $matches[(string)$row['key']] = (string)$row['name'];
        }

        // 2. Exact alias, but exclusively inside the miniature category.
        $st = $this->pdo->prepare("
            SELECT DISTINCT i.key,i.name
            FROM kb_aliases a
            JOIN kb_items i ON i.key=a.item_key
            WHERE i.active=1
              AND i.category_key='miniature'
              AND a.normalized_alias=:alias
        ");
        $st->execute([':alias'=>$needle]);

        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $matches[(string)$row['key']] = (string)$row['name'];
        }

        // 3. Explicit miniature-list context allows presentation words from KB
        // aliases to be stripped:
        //   "Mini Titan"       -> "Titan"
        //   "Mini adelbern"    -> "adelbern"
        //   "Water Djinn mini" -> "water djinn"
        //
        // The result is accepted only when exactly one miniature identity wins.
        $rows = $this->pdo->query("
            SELECT DISTINCT
                i.key,
                i.name,
                a.normalized_alias
            FROM kb_items i
            JOIN kb_aliases a ON a.item_key=i.key
            WHERE i.active=1
              AND i.category_key='miniature'
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $alias = trim((string)($row['normalized_alias'] ?? ''));
            if ($alias === '') continue;

            $short = preg_replace(
                '/^(?:miniature|mini|minipet)\s+|\s+(?:miniature|mini|minipet)$/u',
                '',
                $alias
            ) ?? $alias;

            $short = trim(preg_replace('/\s+/u', ' ', $short) ?? $short);

            if ($short !== $needle) continue;

            $matches[(string)$row['key']] = (string)$row['name'];
        }

        if (count($matches) !== 1) return null;

        return array_values($matches)[0];
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