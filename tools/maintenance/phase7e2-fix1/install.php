<?php
declare(strict_types=1);

/**
 * LittyWatch V5.2 - Phase 7E.2 FIX1
 *
 * Fixes:
 *  1. explicit ded/unded miniature shorthand context survives item matching;
 *  2. bare "dhuum" is no longer a Miniature Dhuum alias;
 *  3. DSR / Dhuum Scythe / Dhuum Soul Reaper => Dhuum's Soul Reaper;
 *  4. Kath Set / Kath Hammer shorthand => existing Kathandrax Hammer catalog item.
 *
 * Important:
 *  - no dedication is inferred;
 *  - only explicit ded/unded source text is encoded in contextual aliases;
 *  - no new fantasy catalog items are created.
 */

$root = dirname(__DIR__, 3);
$file = $root . '/app/Parser/Catalog.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: Catalog.php ontbreekt: {$file}\n");
    exit(1);
}

$code = file_get_contents($file);
if ($code === false) {
    fwrite(STDERR, "ERROR: Catalog.php kon niet worden gelezen.\n");
    exit(1);
}

$marker = 'LITTYWATCH_PHASE7E2_FIX1_DEDICATION_DHUUM_KATH';
if (str_contains($code, $marker)) {
    echo "Phase 7E.2 FIX1 staat al in Catalog.php.\n";
    exit(0);
}

if (!str_contains($code, 'LITTYWATCH_PHASE7E2_MINIATURE_CANONICAL_CLEANUP')) {
    fwrite(STDERR, "ERROR: Phase 7E.2 basispatch ontbreekt. Installeer 7E.2 eerst.\n");
    exit(1);
}

$backupDir = $root . '/storage/backups/phase7e2-fix1-' . date('Ymd-His');
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden aangemaakt.\n");
    exit(1);
}
if (!copy($file, $backupDir . '/Catalog.php')) {
    fwrite(STDERR, "ERROR: backup van Catalog.php mislukt.\n");
    exit(1);
}

// -------------------------------------------------------------------------
// 1) Remove the dangerous bare "dhuum" alias from Miniature Dhuum.
// -------------------------------------------------------------------------
$before = $code;
$code = preg_replace(
    "/('miniature dhuum'\s*=>\s*\[\s*'miniature dhuum'\s*,\s*'mini dhuum'\s*,\s*'mini duum'\s*),\s*'dhuum'\s*,?/u",
    "$1,",
    $code,
    1,
    $countDhuum
);
if (($countDhuum ?? 0) !== 1) {
    @copy($backupDir . '/Catalog.php', $file);
    fwrite(STDERR, "ERROR: verwachte Phase 7E.2 bare-dhuum alias niet exact 1x gevonden.\n");
    exit(1);
}

// -------------------------------------------------------------------------
// 2) Extend the existing 7E.2 helper after $aliasesByCanonical.
//    We add:
//      - explicit ded/unded compound aliases for every Phase7E2 miniature alias;
//      - DSR/scythe aliases to existing Dhuum's Soul Reaper;
//      - Kath shorthand to existing Kathandrax Hammer.
// -------------------------------------------------------------------------
$needle = "        foreach (\$items as \$index => \$item) {";
$pos = strpos($code, $needle);
if ($pos === false) {
    @copy($backupDir . '/Catalog.php', $file);
    fwrite(STDERR, "ERROR: Phase 7E.2 helper-loop niet gevonden.\n");
    exit(1);
}

$inject = <<<'PHP'
        // LITTYWATCH_PHASE7E2_FIX1_DEDICATION_DHUUM_KATH
        //
        // Explicit dedication context is NOT an inference. These compound aliases
        // merely make sure the source token "ded"/"unded" remains inside the match
        // span, so the existing ParserEngine dedication extractor can see it.
        foreach ($aliasesByCanonical as $canonical => $baseAliases) {
            if (!str_starts_with($canonical, 'miniature ')) continue;

            $contextAliases = [];
            foreach ($baseAliases as $alias) {
                $alias = trim((string)$alias);
                if ($alias === '') continue;
                if (preg_match('/\b(?:ded|unded|dedicated|undedicated)\b/iu', $alias)) continue;

                $contextAliases[] = 'unded ' . $alias;
                $contextAliases[] = 'undedi ' . $alias; // common clipped Kamadan shorthand
                $contextAliases[] = 'undedicated ' . $alias;
                $contextAliases[] = 'ded ' . $alias;
                $contextAliases[] = 'dedicated ' . $alias;
            }
            $aliasesByCanonical[$canonical] = array_values(array_unique(array_merge(
                $baseAliases,
                $contextAliases
            )));
        }

        // Existing canonical market items only; no new catalog rows are manufactured.
        $phase7e2Fix1ExtraAliases = [
            "dhuum's soul reaper" => [
                'dsr',
                'dhuum soul reaper',
                "dhuum's soul reaper",
                'dhuum reaper',
                'dhuum scythe',
            ],
            'kathandrax hammer' => [
                'kath hammer',
                'kath hammers',
                'kath set',
                'kath sets',
                'kathandrax set',
                'kathandrax sets',
                'kath',
            ],
        ];

PHP;

$code = substr($code, 0, $pos) . $inject . substr($code, $pos);

// -------------------------------------------------------------------------
// 3) Inside the existing item loop, attach FIX1 aliases to those canonicals.
//    Place directly after $canonicalLookup is established.
// -------------------------------------------------------------------------
$anchor = "            if (\$name === 'miniature undead prince') \$canonicalLookup = 'miniature undead prince rurik';";
$anchorPos = strpos($code, $anchor);
if ($anchorPos === false) {
    @copy($backupDir . '/Catalog.php', $file);
    fwrite(STDERR, "ERROR: canonicalLookup-anchor van 7E.2 niet gevonden.\n");
    exit(1);
}
$anchorEnd = $anchorPos + strlen($anchor);

$attach = <<<'PHP'


            // Phase 7E.2 FIX1 extra identities.
            if (isset($phase7e2Fix1ExtraAliases[$canonicalLookup])) {
                $existing = is_array($item['aliases'] ?? null) ? $item['aliases'] : [];
                $items[$index]['aliases'] = array_values(array_unique(array_merge(
                    $existing,
                    $phase7e2Fix1ExtraAliases[$canonicalLookup]
                )));
                // Keep local $item in sync because the 7E.2 miniature merge below
                // reads from $item rather than from $items[$index].
                $item = $items[$index];
            }
PHP;

$code = substr($code, 0, $anchorEnd) . $attach . substr($code, $anchorEnd);

// -------------------------------------------------------------------------
// 4) Existing Phase7E2 filter rejected dedication words from aliases.
//    FIX1 allows them ONLY because they are explicit compound context aliases.
//    Replace that exact filter with a no-guessing comment + non-empty return.
// -------------------------------------------------------------------------
$oldFilter = <<<'PHP'
                // Dedication is a variant, never an item identity alias.
                return !preg_match('/\b(?:ded|unded|dedicated|undedicated)\b/iu', $alias);
PHP;

$newFilter = <<<'PHP'
                // Phase 7E.2 FIX1: dedication words are allowed only in the curated
                // compound aliases generated above. They reflect explicit source text;
                // no dedication state is ever invented from an item name alone.
                return true;
PHP;

if (!str_contains($code, $oldFilter)) {
    @copy($backupDir . '/Catalog.php', $file);
    fwrite(STDERR, "ERROR: 7E.2 dedication-alias filter niet gevonden.\n");
    exit(1);
}
$code = str_replace($oldFilter, $newFilter, $code);

// -------------------------------------------------------------------------
// 5) Guard against an accidental bare-dhuum reintroduction.
// -------------------------------------------------------------------------
if (preg_match("/'miniature dhuum'\s*=>\s*\[[^\]]*'dhuum'\s*,?[^\]]*\]/su", $code)) {
    @copy($backupDir . '/Catalog.php', $file);
    fwrite(STDERR, "ERROR: bare 'dhuum' staat na patch nog als Miniature Dhuum alias geregistreerd.\n");
    exit(1);
}

if (file_put_contents($file, $code) === false) {
    @copy($backupDir . '/Catalog.php', $file);
    fwrite(STDERR, "ERROR: schrijven van Catalog.php mislukt; backup teruggezet.\n");
    exit(1);
}

exec('/usr/bin/php -l ' . escapeshellarg($file), $lintOut, $lintCode);
if ($lintCode !== 0) {
    @copy($backupDir . '/Catalog.php', $file);
    fwrite(STDERR, "ERROR: Catalog.php faalt php -l; backup teruggezet.\n");
    fwrite(STDERR, implode("\n", $lintOut) . "\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.2 FIX1 geïnstalleerd.\n";
echo "Backup: {$backupDir}\n";
echo "Fixes:\n";
echo "  - bare Dhuum => NIET langer automatisch Miniature Dhuum\n";
echo "  - DSR / Dhuum Scythe => Dhuum's Soul Reaper\n";
echo "  - Kath Set / Kath Hammer => Kathandrax Hammer\n";
echo "  - expliciet ded/unded blijft in miniature match-context zichtbaar\n";
echo "Dedication-policy: GEEN GOKWERK; alleen expliciete bronwoorden.\n";
echo "Draai nu:\n";
echo "  php -l app/Parser/Catalog.php\n";
echo "  php tools/maintenance/phase7e2-fix1/smoke-test.php\n";
