<?php
declare(strict_types=1);

/**
 * LittyWatch V5.2 - Phase 4D
 *
 * Goals:
 *  - reconcile verified canonical catalog identities missed by the wiki pack
 *  - expand comma/slash miniature lists conservatively
 *  - expand repeated Party/Alcohol/Sweet quantity lists
 *  - expand Ghostly Staff attribute/requirement lists
 *  - keep generic Axe/Shield/Staff/... unresolved
 *
 * Prerequisite: Phase 4C.
 */

$root = dirname(__DIR__, 2);
$parserDir = $root . '/app/Parser';
$dataDir = $root . '/app/Data';

$catalogFile = $parserDir . '/Catalog.php';
$semanticFile = $parserDir . '/SemanticNormalizer.php';
$engineFile = $parserDir . '/ParserEngine.php';
$bundleFile = $parserDir . '/MarketBundleExpander.php';
$supplementFile = $dataDir . '/phase4d-items.json';

foreach ([$catalogFile, $semanticFile, $engineFile, $bundleFile] as $required) {
    if (!is_file($required)) {
        fwrite(STDERR, "ERROR: vereist Phase-4C bestand niet gevonden: {$required}\n");
        exit(1);
    }
}

if (!str_contains((string)file_get_contents($engineFile), 'LITTYWATCH_PHASE4C_BUNDLE_RESOLVER')) {
    fwrite(STDERR, "ERROR: Phase 4C lijkt niet geïnstalleerd (bundle marker ontbreekt).\n");
    exit(1);
}

$stamp = date('Ymd-His');
$backupDir = $root . '/storage/backups/phase4d-' . $stamp;
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden gemaakt: {$backupDir}\n");
    exit(1);
}

foreach ([$catalogFile, $semanticFile, $bundleFile] as $file) {
    if (!copy($file, $backupDir . '/' . basename($file))) {
        fwrite(STDERR, "ERROR: backup mislukt voor {$file}\n");
        exit(1);
    }
}
if (is_file($supplementFile)) @copy($supplementFile, $backupDir . '/' . basename($supplementFile));

function replace_once_4d(string $contents, string $needle, string $replacement, string $label): string
{
    $count = substr_count($contents, $needle);
    if ($count !== 1) {
        throw new RuntimeException("Anchor {$label} verwacht 1x, gevonden {$count}x.");
    }
    return str_replace($needle, $replacement, $contents);
}

function write_checked_4d(string $file, string $contents): void
{
    if (file_put_contents($file, $contents) === false) {
        throw new RuntimeException("Kon {$file} niet schrijven.");
    }
}

$supplement = [
    [
        'key'=>'ghozers-key',
        'name'=>"Ghozer's Key",
        'category'=>'unique_items',
        'aliases'=>["Ghozer's Key","Ghozers Key","Ghozer´s Key","Ghozer’s Key"],
    ],
    [
        'key'=>'miniature-ghostly-hero',
        'name'=>'Miniature Ghostly Hero',
        'category'=>'miniatures',
        'aliases'=>['Miniature Ghostly Hero','Ghostly Hero mini','mini Ghostly Hero'],
    ],
    [
        'key'=>'miniature-undead-prince',
        'name'=>'Miniature Undead Prince',
        'category'=>'miniatures',
        'aliases'=>['Miniature Undead Prince','Miniature Undead Prince Rurik','Undead Prince mini','Undead Prince Rurik mini'],
    ],
    [
        'key'=>'miniature-kuunavang',
        'name'=>'Miniature Kuunavang',
        'category'=>'miniatures',
        'aliases'=>['Miniature Kuunavang','Kuunavang mini','Kuuna mini','kuunaavang mini'],
    ],
    [
        'key'=>'miniature-zhed-shadowhoof',
        'name'=>'Miniature Zhed Shadowhoof',
        'category'=>'miniatures',
        'aliases'=>['Miniature Zhed Shadowhoof','Zhed mini','mini Zhed'],
    ],
    [
        'key'=>'miniature-rift-warden',
        'name'=>'Miniature Rift Warden',
        'category'=>'miniatures',
        'aliases'=>['Miniature Rift Warden','Rift Warden mini','mini Rift Warden'],
    ],
    [
        'key'=>'miniature-ecclesiate-xun-rao',
        'name'=>'Miniature Ecclesiate Xun Rao',
        'category'=>'miniatures',
        'aliases'=>['Miniature Ecclesiate Xun Rao','Xun Rao mini','Preacher Xun Rao mini','Ecclesiate Xun Rao mini'],
    ],
    [
        'key'=>'miniature-dagnar-stonepate',
        'name'=>'Miniature Dagnar Stonepate',
        'category'=>'miniatures',
        'aliases'=>['Miniature Dagnar Stonepate','Dagnar mini',"Mini's Dagnar",'mini Dagnar'],
    ],
    ['key'=>'miniature-lich','name'=>'Miniature Lich','category'=>'miniatures','aliases'=>['Miniature Lich','Lich mini','mini Lich']],
    ['key'=>'miniature-naga','name'=>'Miniature Naga','category'=>'miniatures','aliases'=>['Miniature Naga','Naga mini']],
    ['key'=>'miniature-oni','name'=>'Miniature Oni','category'=>'miniatures','aliases'=>['Miniature Oni','Oni mini']],
    ['key'=>'miniature-shiroken-assassin','name'=>"Miniature Shiro'ken Assassin",'category'=>'miniatures','aliases'=>["Miniature Shiro'ken Assassin","Shiro'ken Assassin mini"]],
    ['key'=>'miniature-vizu','name'=>'Miniature Vizu','category'=>'miniatures','aliases'=>['Miniature Vizu','Vizu mini']],
    ['key'=>'miniature-shiro','name'=>'Miniature Shiro','category'=>'miniatures','aliases'=>['Miniature Shiro','Shiro mini']],
    ['key'=>'miniature-water-djinn','name'=>'Miniature Water Djinn','category'=>'miniatures','aliases'=>['Miniature Water Djinn','Water Djinn mini']],
    ['key'=>'miniature-zhu-hanuku','name'=>'Miniature Zhu Hanuku','category'=>'miniatures','aliases'=>['Miniature Zhu Hanuku','Zhu Hanuku mini']],
    ['key'=>'miniature-black-beast-of-aaaaarrrrrrggghhh','name'=>'Miniature Black Beast of Aaaaarrrrrrggghhh','category'=>'miniatures','aliases'=>['Miniature Black Beast of Aaaaarrrrrrggghhh','Black Beast mini']],
    ['key'=>'miniature-king-adelbern','name'=>'Miniature King Adelbern','category'=>'miniatures','aliases'=>['Miniature King Adelbern','King Adelbern mini']],
    ['key'=>'miniature-destroyer-of-flesh','name'=>'Miniature Destroyer of Flesh','category'=>'miniatures','aliases'=>['Miniature Destroyer of Flesh','Destroyer mini','Destroyer of Flesh mini']],
    ['key'=>'miniature-varesh','name'=>'Miniature Varesh','category'=>'miniatures','aliases'=>['Miniature Varesh','Varesh mini','Varesh Ossa mini']],
    [
        'key'=>'champions-zaishen-strongbox',
        'name'=>"Champion's Zaishen Strongbox",
        'category'=>'pvp_rewards',
        'aliases'=>["Champion's Zaishen Strongbox",'Champion Zaishen Strongbox','Champion Zaishen Strongboxes'],
    ],
    [
        'key'=>'strategists-zaishen-strongbox',
        'name'=>"Strategist's Zaishen Strongbox",
        'category'=>'pvp_rewards',
        'aliases'=>["Strategist's Zaishen Strongbox",'Strategist Zaishen Strongbox','Strategist Zaishen Strongboxes','zaishen strat strongbox'],
    ],
    [
        'key'=>'zaishen-key',
        'name'=>'Zaishen Key',
        'category'=>'keys',
        'aliases'=>['Zaishen Key','Zaishen Keys','zkey','zkeys'],
        'market_quote_basis'=>'each',
        'market_quote_size'=>1,
        'market_display_basis'=>'each',
    ],
];

$bundleClass = <<<'PHP'
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
PHP;

try {
    if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        throw new RuntimeException("Datamap ontbreekt en kon niet worden gemaakt: {$dataDir}");
    }

    write_checked_4d(
        $supplementFile,
        json_encode($supplement, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    );

    // Catalog supplement: merge verified Phase 4D identities after the normal
    // knowledge pack, before DB knowledge is merged.
    $catalog = (string)file_get_contents($catalogFile);
    if (!str_contains($catalog, 'LITTYWATCH_PHASE4D_CATALOG_RECONCILIATION')) {
        $anchor = "        if (is_file(\$packAliasesPath)) {\n"
                . "            \$this->items = \$this->mergeAliases(\$this->items, \$this->loadJson(\$packAliasesPath));\n"
                . "        }\n";
        $replacement = $anchor
            . "        // LITTYWATCH_PHASE4D_CATALOG_RECONCILIATION\n"
            . "        \$phase4dItemsPath = \$dataDir . '/phase4d-items.json';\n"
            . "        if (is_file(\$phase4dItemsPath)) {\n"
            . "            \$this->items = \$this->mergeItems(\$this->items, \$this->loadJson(\$phase4dItemsPath));\n"
            . "        }\n";
        $catalog = replace_once_4d($catalog, $anchor, $replacement, 'Catalog knowledge-pack merge');
        write_checked_4d($catalogFile, $catalog);
    }

    // Exact corrections that belong before item matching.
    $semantic = (string)file_get_contents($semanticFile);
    if (!str_contains($semantic, 'LITTYWATCH_PHASE4D_CANONICAL_FIXES')) {
        $anchor = "        // LITTYWATCH_PHASE4C_ALIAS_CLEANUP_END\n";
        $fixes = <<<'PHP'
        // LITTYWATCH_PHASE4D_CANONICAL_FIXES
        // Official item name is Miniature Undead Prince (not "... Rurik").
        $text = preg_replace('/\bMiniature\s+Undead\s+Prince(?:\s+Rurik)?\b/iu', 'Miniature Undead Prince', $text) ?? $text;
        $text = preg_replace('/\bPreacher\s+Xun\s+Rao\s+mini(?:ature|pet)?\b/iu', 'Miniature Ecclesiate Xun Rao', $text) ?? $text;
        $text = preg_replace('/\bMini(?:ature)?[\'’]s?\s+Dagnar\b|\bDagnar\s+mini(?:ature|pet)?\b/iu', 'Miniature Dagnar Stonepate', $text) ?? $text;

        // Strongbox community shorthand.
        $text = preg_replace('/\bChampion(?:\'s)?\s+Zaishen\s+Strongboxes?\b/iu', "Champion's Zaishen Strongbox", $text) ?? $text;
        $text = preg_replace('/\b(?:zaishen\s+)?strat(?:egist)?(?:\'s)?\s+(?:zaishen\s+)?strongboxes?\b/iu', "Strategist's Zaishen Strongbox", $text) ?? $text;
        // LITTYWATCH_PHASE4D_CANONICAL_FIXES_END

PHP;
        $semantic = replace_once_4d($semantic, $anchor, $fixes . $anchor, 'Phase 4C alias marker');
        write_checked_4d($semanticFile, $semantic);
    }

    // MarketBundleExpander is Phase-4C-owned, so replace it atomically with V2.
    write_checked_4d($bundleFile, $bundleClass);

    foreach ([$catalogFile, $semanticFile, $bundleFile, $engineFile] as $lintFile) {
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($lintFile) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            throw new RuntimeException("PHP syntaxcheck faalde voor {$lintFile}:\n" . implode("\n", $out));
        }
    }

    // Validate JSON separately.
    json_decode((string)file_get_contents($supplementFile), true, flags: JSON_THROW_ON_ERROR);

    echo "OK: LittyWatch V5.2 Phase 4D geïnstalleerd.\n";
    echo "Backup: {$backupDir}\n";
    echo "Catalog supplement: app/Data/phase4d-items.json\n";
    echo "MarketBundleExpander: V2 actief\n\n";
    echo "Draai nu dezelfde volledige reparse als na Phase 4C.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Rollback vanuit {$backupDir}...\n");

    foreach ([$catalogFile, $semanticFile, $bundleFile] as $file) {
        $backup = $backupDir . '/' . basename($file);
        if (is_file($backup)) @copy($backup, $file);
    }

    $supplementBackup = $backupDir . '/' . basename($supplementFile);
    if (is_file($supplementBackup)) {
        @copy($supplementBackup, $supplementFile);
    } elseif (is_file($supplementFile)) {
        @unlink($supplementFile);
    }
    exit(1);
}
