<?php
declare(strict_types=1);

/**
 * LittyWatch V5.2 - Phase 4C installer
 *
 * Adds:
 *  1) miniature list/context inheritance
 *  2) high-confidence bundle expansion
 *  3) exact residual alias cleanup
 *
 * Run from the LittyWatch project after extracting this ZIP over the project:
 *   php tools/maintenance/install-phase4c.php
 */

$root = dirname(__DIR__, 2);
$parserDir = $root . '/app/Parser';
$semanticFile = $parserDir . '/SemanticNormalizer.php';
$contextFile = $parserDir . '/ContextualSegmentExpander.php';
$engineFile = $parserDir . '/ParserEngine.php';
$bundleFile = $parserDir . '/MarketBundleExpander.php';

foreach ([$semanticFile, $contextFile, $engineFile] as $required) {
    if (!is_file($required)) {
        fwrite(STDERR, "ERROR: vereist bestand niet gevonden: {$required}\n");
        exit(1);
    }
}

$stamp = date('Ymd-His');
$backupDir = $root . '/storage/backups/phase4c-' . $stamp;
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden gemaakt: {$backupDir}\n");
    exit(1);
}

$targets = [$semanticFile, $contextFile, $engineFile];
if (is_file($bundleFile)) $targets[] = $bundleFile;
foreach ($targets as $file) {
    if (!copy($file, $backupDir . '/' . basename($file))) {
        fwrite(STDERR, "ERROR: backup mislukt voor {$file}\n");
        exit(1);
    }
}

function replace_once(string $contents, string $needle, string $replacement, string $label): string
{
    $count = substr_count($contents, $needle);
    if ($count !== 1) {
        throw new RuntimeException("Anchor {$label} verwacht 1x, gevonden {$count}x.");
    }
    return str_replace($needle, $replacement, $contents);
}

function write_checked(string $file, string $contents): void
{
    if (file_put_contents($file, $contents) === false) {
        throw new RuntimeException("Kon {$file} niet schrijven.");
    }
}

$bundleClass = <<<'PHP'
<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

/**
 * Phase 4C: expands a few high-confidence market bundles before generic
 * grammar segmentation. It deliberately refuses broad groups that cannot be
 * mapped to concrete catalog identities.
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

    /** @return list<string>|null */
    public function expand(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') return null;

        $points = $this->expandPointBundle($text);
        if ($points !== null) return $points;

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

        $tomes = $this->expandProfessionTomeBundle($text);
        if ($tomes !== null) return $tomes;

        // Important: a generic "300+ Elite / Normal Tomes" pool contains no
        // profession identity. Do not invent a catalog item for it.
        return null;
    }

    /** @return list<string>|null */
    private function expandPointBundle(string $text): ?array
    {
        if (!preg_match(
            '/\b((?:party|sweet|alc(?:ohol)?)(?:\s*\/\s*(?:party|sweet|alc(?:ohol)?)){1,2})\b(.*)$/iu',
            $text,
            $m,
            PREG_OFFSET_CAPTURE
        )) {
            return null;
        }

        $bundle = $m[1][0];
        $offset = $m[1][1];
        $before = trim(substr($text, 0, $offset));
        $after = trim($m[2][0]);

        $map = [
            'party'=>'Party Points',
            'sweet'=>'Sweet Points',
            'alc'=>'Alcohol Points',
            'alcohol'=>'Alcohol Points',
        ];
        $items = [];
        foreach (preg_split('/\s*\/\s*/u', $bundle) ?: [] as $token) {
            $key = mb_strtolower(trim($token));
            if (!isset($map[$key])) return null;
            $items[$map[$key]] = true;
        }
        if (count($items) < 2) return null;

        return $this->withSharedContext(array_keys($items), $before, $after);
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

    /**
     * Expands e.g.
     * "1500 Elite tomes (250x ele, ranger, war, mes, nec, mo) 140a"
     * to concrete profession tome identities.
     *
     * The trailing aggregate bundle price is intentionally not copied to each
     * profession. That would turn 140a for 1500 tomes into six fake 140a lots.
     *
     * @return list<string>|null
     */
    private function expandProfessionTomeBundle(string $text): ?array
    {
        if (!preg_match(
            '/\b(?:(\d+)\s+)?(elite|normal|regular)\s+tomes?\s*\(([^)]+)\)/iu',
            $text,
            $m,
            PREG_OFFSET_CAPTURE
        )) {
            return null;
        }

        $kind = mb_strtolower($m[2][0]);
        $inside = trim($m[3][0]);
        $perProfessionQuantity = null;

        if (preg_match('/^\s*(\d+)\s*x\s*/iu', $inside, $qm)) {
            $perProfessionQuantity = (int)$qm[1];
            $inside = preg_replace('/^\s*\d+\s*x\s*/iu', '', $inside, 1) ?? $inside;
        }

        $professions = [];
        foreach (preg_split('/\s*(?:,|\/|&|\band\b)\s*/iu', $inside) ?: [] as $token) {
            $token = trim(preg_replace('/^\d+\s*x\s*/iu', '', $token) ?? $token);
            if ($token === '') continue;
            $key = mb_strtolower($token);
            if (!isset(self::PROFESSIONS[$key])) continue;
            $professions[self::PROFESSIONS[$key]] = true;
        }
        if ($professions === []) return null;

        $out = [];
        foreach (array_keys($professions) as $profession) {
            $item = $kind === 'elite'
                ? 'Elite ' . $profession . ' Tome'
                : $profession . ' Tome';
            if ($perProfessionQuantity !== null && $perProfessionQuantity > 0) {
                $item = $perProfessionQuantity . ' ' . $item;
            }
            $out[] = $item;
        }
        return $out;
    }

    /** @param list<string> $items @return list<string> */
    private function withSharedContext(array $items, string $before, string $after): array
    {
        // Keep only harmless leading quantity/context, never duplicate another
        // concrete item that happened to precede the bundle.
        if ($before !== '' && !preg_match('/^(?:\d+(?:[.,]\d+)?\s*)?$/u', $before)) {
            $before = '';
        }

        $out = [];
        foreach ($items as $item) {
            $out[] = trim(($before !== '' ? $before . ' ' : '') . $item . ($after !== '' ? ' ' . $after : ''));
        }
        return $out;
    }
}
PHP;

try {
    // ---------------------------------------------------------------------
    // 1. SemanticNormalizer: exact aliases + safe miniature aliases
    // ---------------------------------------------------------------------
    $semantic = file_get_contents($semanticFile);
    if ($semantic === false) throw new RuntimeException('SemanticNormalizer.php kon niet worden gelezen.');

    if (!str_contains($semantic, 'LITTYWATCH_PHASE4C_ALIAS_CLEANUP')) {
        $semantic = replace_once(
            $semantic,
            "        \$text = trim(preg_replace('/\\s+/u',' ', \$text) ?? \$text);\n",
            "        \$text = trim(preg_replace('/\\s+/u',' ', \$text) ?? \$text);\n"
            . "        // LITTYWATCH_PHASE4C_APOSTROPHES\n"
            . "        \$text = strtr(\$text, [\"’\"=>\"'\", \"‘\"=>\"'\", \"´\"=>\"'\", \"`\"=>\"'\"]);\n",
            'semantic whitespace'
        );

        $phase4cAliases = <<<'PHP'
        // LITTYWATCH_PHASE4C_ALIAS_CLEANUP
        // Exact, observed residual aliases. Keep these narrow: a generic weapon
        // family must never become a fabricated skin.
        $text = preg_replace('/\bGhozer[\'’´`]?s\s+Key(?:\s+for)?(?=\s+(?:\d+(?:[.,]\d+)?\s*(?:e|a|k|g)\b|$))/iu', "Ghozer's Key", $text) ?? $text;
        $text = preg_replace('/\bGold(?:en)?\s+Flames?\b/iu', 'Golden Flame of Balthazar', $text) ?? $text;
        $text = preg_replace('/\bAcient\s*Horn\s*bow\b|\bAcientHornbow\b/iu', 'Ancient Hornbow', $text) ?? $text;

        // 2025 profession weapon upgrades: observed market shorthand names the
        // attribute and weapon family instead of the actual upgrade component.
        $text = preg_replace('/\bSR\s*\+\s*[45]\s+Spea(?:r)?\b/iu', 'Spear Grip of the Necromancer', $text) ?? $text;
        $text = preg_replace('/\bBow\s+ES\s*\+\s*(?:5|12|13|14|15)\b/iu', 'Bow Grip of the Elementalist', $text) ?? $text;

        // +30HP only becomes Fortitude when the component family is explicit.
        $fortitude = [
            'axe'=>'Axe Grip of Fortitude',
            'bow'=>'Bow Grip of Fortitude',
            'hammer'=>'Hammer Grip of Fortitude',
            'spear'=>'Spear Grip of Fortitude',
            'scythe'=>'Scythe Grip of Fortitude',
            'sword'=>'Sword Pommel of Fortitude',
            'dagger'=>'Dagger Handle of Fortitude',
            'daggers'=>'Dagger Handle of Fortitude',
            'shield'=>'Shield Handle of Fortitude',
        ];
        $text = preg_replace_callback(
            '/\b(axe|bow|hammer|spear|scythe|sword|daggers?|shield)\s*(?:grip|pommel|handle)?\s*\+?\s*30\s*hp\b|\b\+?\s*30\s*hp\s+(axe|bow|hammer|spear|scythe|sword|daggers?|shield)(?:\s+(?:grip|pommel|handle))?\b/iu',
            static function(array $m) use ($fortitude): string {
                $family = mb_strtolower((string)($m[1] !== '' ? $m[1] : $m[2]));
                return $fortitude[$family] ?? $m[0];
            },
            $text
        ) ?? $text;
        $text = preg_replace('/\b(?:\+?\s*30\s*hp\s+)?staff\s+wra(?:p|pping)(?:\s+\+?\s*30\s*hp)?\b/iu', 'Staff Wrapping of Fortitude', $text) ?? $text;

        // Targeted miniature names that are unsafe as bare aliases because NPCs
        // and non-market concepts share the same words. Require mini/ded/unded.
        $miniatureAliases = [
            'ghostly\s+hero' => 'Miniature Ghostly Hero',
            'kuuna(?:vang)?' => 'Miniature Kuunavang',
            'zhed(?:\s+shadowhoof)?' => 'Miniature Zhed Shadowhoof',
            'rift\s+warden' => 'Miniature Rift Warden',
            '(?:undead\s+prince(?:\s+rurik)?|prince\s+rurik)' => 'Miniature Undead Prince Rurik',
        ];
        foreach ($miniatureAliases as $aliasPattern => $canonicalMiniature) {
            $text = preg_replace_callback(
                '/\b(unded(?:icated)?|ded(?:icated)?)\s+(?:mini(?:ature)?\s+)?(' . $aliasPattern . ')\b/iu',
                static function(array $m) use ($canonicalMiniature): string {
                    $state = str_starts_with(mb_strtolower($m[1]), 'un') ? 'unded' : 'ded';
                    return $canonicalMiniature . ' ' . $state;
                },
                $text
            ) ?? $text;
            $text = preg_replace('/\bmini(?:ature)?\s+(' . $aliasPattern . ')\b/iu', $canonicalMiniature, $text) ?? $text;
        }
        // LITTYWATCH_PHASE4C_ALIAS_CLEANUP_END

PHP;
        $semantic = replace_once(
            $semantic,
            "        // Phase 2F: canonical names must remain idempotent after repeated normalization.\n",
            $phase4cAliases . "        // Phase 2F: canonical names must remain idempotent after repeated normalization.\n",
            'semantic Phase 2F'
        );

        // Correct an older non-canonical name that still existed in Phase 2I.
        $semantic = str_replace(
            "\$text = preg_replace('/\\bstygian\\s+gems?\\b/iu', 'Stygian Gem', \$text) ?? \$text;",
            "\$text = preg_replace('/\\bstygian\\s+(?:gems?|gemstones?)\\b/iu', 'Stygian Gemstone', \$text) ?? \$text;",
            $semantic
        );
        write_checked($semanticFile, $semantic);
    }

    // ---------------------------------------------------------------------
    // 2. ContextualSegmentExpander: miniature list/context family
    // ---------------------------------------------------------------------
    $context = file_get_contents($contextFile);
    if ($context === false) throw new RuntimeException('ContextualSegmentExpander.php kon niet worden gelezen.');

    if (!str_contains($context, 'LITTYWATCH_PHASE4C_MINIATURE_CONTEXT')) {
        $context = replace_once(
            $context,
            "        \$pendingHeader = null;\n",
            "        \$pendingHeader = null;\n"
            . "        // LITTYWATCH_PHASE4C_MINIATURE_CONTEXT\n"
            . "        \$activeMiniatureState = null;\n",
            'context state'
        );

        $miniDetect = <<<'PHP'
            // Phase 4C: "unded minis | Bone Dragon, Zhed, Kuuna" is a typed
            // list. This context must win over an accidental bare-name match.
            $miniatureHeader = $this->miniatureHeader($segment);
            if ($miniatureHeader !== null) {
                if ($pendingHeader !== null) { $out[] = $pendingHeader; $pendingHeader = null; }
                $activeFamily = 'Miniature';
                $activeMiniatureState = $miniatureHeader['state'];
                $activeItem = null;
                $activeRequirement = null;
                continue;
            }

PHP;
        $context = replace_once(
            $context,
            "            \$family = \$this->familyHeader(\$segment);\n",
            $miniDetect . "            \$family = \$this->familyHeader(\$segment);\n",
            'context family detection'
        );

        $context = replace_once(
            $context,
            "                \$activeFamily = \$family['family'];\n                \$activeItem = null;\n",
            "                \$activeFamily = \$family['family'];\n"
            . "                \$activeMiniatureState = null;\n"
            . "                \$activeItem = null;\n",
            'context reset miniature state'
        );

        $context = str_replace(
            "\$this->attachFamily(\$part, \$activeFamily, \$activeRequirement)",
            "\$this->attachFamily(\$part, \$activeFamily, \$activeRequirement, \$activeMiniatureState)",
            $context
        );
        $context = str_replace(
            "\$this->attachFamily(\$segment, \$activeFamily, \$activeRequirement)",
            "\$this->attachFamily(\$segment, \$activeFamily, \$activeRequirement, \$activeMiniatureState)",
            $context
        );

        $priorityBlock = <<<'PHP'
            // Phase 4C: while a miniature list is active, try "Miniature X"
            // before matching the bare X. This is the key fix for Ghostly Hero,
            // Zhed, Kuuna/Kuunavang, Rift Warden, Prince Rurik, etc.
            if ($activeFamily === 'Miniature') {
                $miniCandidate = $this->attachFamily($segment, 'Miniature', null, $activeMiniatureState);
                $miniMatches = $this->items->matchAll($miniCandidate);
                if ($miniMatches !== []) {
                    if ($pendingHeader !== null) { $pendingHeader = null; }
                    $out[] = $miniCandidate;
                    $activeItem = (string)$miniMatches[0]['item'];
                    continue;
                }
            }

PHP;
        $context = replace_once(
            $context,
            "            \$matches = \$this->items->matchAll(\$segment);\n",
            $priorityBlock . "            \$matches = \$this->items->matchAll(\$segment);\n",
            'context match priority'
        );

        $context = replace_once(
            $context,
            "                \$inferredFamily = \$this->familyFromItem(\$activeItem);\n                if (\$inferredFamily !== null) \$activeFamily = \$inferredFamily;\n",
            "                \$inferredFamily = \$this->familyFromItem(\$activeItem);\n"
            . "                if (\$inferredFamily !== null) {\n"
            . "                    \$activeFamily = \$inferredFamily;\n"
            . "                    if (\$inferredFamily !== 'Miniature') \$activeMiniatureState = null;\n"
            . "                } elseif (\$activeFamily === 'Miniature') {\n"
            . "                    // A concrete non-miniature item ends miniature list context.\n"
            . "                    \$activeFamily = null;\n"
            . "                    \$activeMiniatureState = null;\n"
            . "                }\n",
            'context inferred family'
        );

        $context = str_replace(
            "foreach (['Staff','Wand','Bow','Sword','Axe','Hammer','Shield','Spear','Scythe','Daggers','Dagger','Focus','Tonic'] as \$family)",
            "foreach (['Miniature','Staff','Wand','Bow','Sword','Axe','Hammer','Shield','Spear','Scythe','Daggers','Dagger','Focus','Tonic'] as \$family)",
            $context
        );

        $miniMethod = <<<'PHP'
    /** @return array{state:?string}|null */
    private function miniatureHeader(string $segment): ?array
    {
        $clean = trim($segment);
        if (!preg_match(
            '/^(?:(unded(?:icated)?|ded(?:icated)?)\s+)?(?:(?:bday|birthday|cele(?:stial)?)\s+)?(?:miniatures?|minis?|mini\s*pets?|minipets?)(?:\s+list)?$/iu',
            $clean,
            $m
        )) {
            return null;
        }
        $state = null;
        if (!empty($m[1])) $state = str_starts_with(mb_strtolower($m[1]), 'un') ? 'unded' : 'ded';
        return ['state'=>$state];
    }

PHP;
        $context = replace_once(
            $context,
            "    /** @return array{family:string,requirement:?string}|null */\n    private function familyHeader",
            $miniMethod . "    /** @return array{family:string,requirement:?string}|null */\n    private function familyHeader",
            'context miniature method'
        );

        $oldAttachSig = "    private function attachFamily(string \$segment, string \$family, ?string \$requirement): string\n    {\n"
            . "        \$segment = trim(\$segment);\n";
        $newAttachSig = "    private function attachFamily(string \$segment, string \$family, ?string \$requirement, ?string \$miniatureState = null): string\n    {\n"
            . "        \$segment = trim(\$segment);\n"
            . "        if (\$family === 'Miniature') {\n"
            . "            \$localState = \$miniatureState;\n"
            . "            if (preg_match('/^(unded(?:icated)?|ded(?:icated)?)\\s+/iu', \$segment, \$sm)) {\n"
            . "                \$localState = str_starts_with(mb_strtolower(\$sm[1]), 'un') ? 'unded' : 'ded';\n"
            . "                \$segment = trim(preg_replace('/^(?:unded(?:icated)?|ded(?:icated)?)\\s+/iu', '', \$segment, 1) ?? \$segment);\n"
            . "            }\n"
            . "            \$segment = trim(preg_replace('/^mini(?:ature)?\\s+/iu', '', \$segment, 1) ?? \$segment);\n"
            . "            \$candidate = trim('Miniature ' . \$segment . (\$localState !== null ? ' ' . \$localState : ''));\n"
            . "            return \$candidate;\n"
            . "        }\n";
        $context = replace_once($context, $oldAttachSig, $newAttachSig, 'context attachFamily');

        write_checked($contextFile, $context);
    }

    // ---------------------------------------------------------------------
    // 3. MarketBundleExpander + ParserEngine integration
    // ---------------------------------------------------------------------
    write_checked($bundleFile, $bundleClass);

    $engine = file_get_contents($engineFile);
    if ($engine === false) throw new RuntimeException('ParserEngine.php kon niet worden gelezen.');

    if (!str_contains($engine, 'LITTYWATCH_PHASE4C_BUNDLE_RESOLVER')) {
        $engine = replace_once(
            $engine,
            "    private SharedOfferListExpander \$sharedOfferListExpander;\n",
            "    private SharedOfferListExpander \$sharedOfferListExpander;\n"
            . "    private MarketBundleExpander \$marketBundleExpander;\n",
            'engine property'
        );
        $engine = replace_once(
            $engine,
            "        \$this->sharedOfferListExpander = new SharedOfferListExpander();\n",
            "        \$this->sharedOfferListExpander = new SharedOfferListExpander();\n"
            . "        \$this->marketBundleExpander = new MarketBundleExpander();\n",
            'engine constructor'
        );
        $engine = replace_once(
            $engine,
            "            \$sharedListSegments = \$this->sharedOfferListExpander->expand(\$blockText);\n",
            "            // LITTYWATCH_PHASE4C_BUNDLE_RESOLVER\n"
            . "            \$phase4cBundleSegments = \$this->marketBundleExpander->expand(\$blockText);\n"
            . "            \$sharedListSegments = \$phase4cBundleSegments ?? \$this->sharedOfferListExpander->expand(\$blockText);\n",
            'engine bundle call'
        );
        write_checked($engineFile, $engine);
    }

    // Syntax checks before considering installation complete.
    $lintFiles = [$semanticFile, $contextFile, $engineFile, $bundleFile];
    foreach ($lintFiles as $lintFile) {
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($lintFile) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            throw new RuntimeException("PHP syntaxcheck faalde voor {$lintFile}:\n" . implode("\n", $out));
        }
    }

    echo "OK: LittyWatch V5.2 Phase 4C geïnstalleerd.\n";
    echo "Backup: {$backupDir}\n";
    echo "Nieuw: app/Parser/MarketBundleExpander.php\n";
    echo "\n";
    echo "Daarna aanbevolen:\n";
    echo "  php tools/maintenance/reparse-all.php\n";
    echo "of gebruik dezelfde reparse-opdracht die je na Phase 4B hebt gebruikt.\n";
    echo "\n";
    echo "Controleer vervolgens catalog_first_unresolved en catalog_match.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Rollback vanuit {$backupDir}...\n");
    foreach ([$semanticFile, $contextFile, $engineFile] as $file) {
        $backup = $backupDir . '/' . basename($file);
        if (is_file($backup)) @copy($backup, $file);
    }
    $bundleBackup = $backupDir . '/' . basename($bundleFile);
    if (is_file($bundleBackup)) {
        @copy($bundleBackup, $bundleFile);
    } elseif (is_file($bundleFile)) {
        @unlink($bundleFile);
    }
    exit(1);
}
