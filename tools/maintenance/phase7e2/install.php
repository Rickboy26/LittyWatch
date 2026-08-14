<?php
declare(strict_types=1);

/**
 * LittyWatch V5.2 - Phase 7E.2 Miniature Canonical Cleanup
 *
 * Adds a curated alias layer to Catalog.php. This phase only canonicalizes
 * miniature identity. It deliberately does NOT infer dedication state.
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

$marker = 'LITTYWATCH_PHASE7E2_MINIATURE_CANONICAL_CLEANUP';
if (str_contains($code, $marker)) {
    echo "Phase 7E.2 staat al in Catalog.php.\n";
    exit(0);
}

$backupDir = $root . '/storage/backups/phase7e2-' . date('Ymd-His');
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden aangemaakt.\n");
    exit(1);
}
if (!copy($file, $backupDir . '/Catalog.php')) {
    fwrite(STDERR, "ERROR: backup van Catalog.php mislukt.\n");
    exit(1);
}

// Keep this phase independent of constructor layout changes from earlier phases:
// decorate the public items() result and add one private helper method.
$getterPattern = '/public\s+function\s+items\s*\(\s*\)\s*:\s*array\s*\{\s*return\s+\$this->items\s*;\s*\}/m';
if (!preg_match($getterPattern, $code)) {
    fwrite(STDERR, "ERROR: Catalog::items() getter niet gevonden; niets gewijzigd.\n");
    exit(1);
}

$newGetter = <<<'PHP'
public function items(): array
    {
        // LITTYWATCH_PHASE7E2_MINIATURE_CANONICAL_CLEANUP
        // Identity cleanup only: dedication (ded/unded) is intentionally not inferred here.
        return $this->applyPhase7E2MiniatureAliases($this->items);
    }
PHP;
$code = preg_replace($getterPattern, $newGetter, $code, 1);

$insertNeedle = '    public function knowledgeBase()';
$insertPos = strpos($code, $insertNeedle);
if ($insertPos === false) {
    // Fallback: insert before mergeItems(), which is present in the known V5.2 Catalog.
    $insertNeedle = '    private function mergeItems(';
    $insertPos = strpos($code, $insertNeedle);
}
if ($insertPos === false) {
    @copy($backupDir . '/Catalog.php', $file);
    fwrite(STDERR, "ERROR: veilige invoegpositie voor Phase 7E.2 helper niet gevonden.\n");
    exit(1);
}

$helper = <<<'PHP'

    /**
     * Phase 7E.2: curated miniature canonical aliases.
     *
     * Rules:
     * - only attach aliases to canonical Miniature catalog records that already exist;
     * - never create a new/fantasy item;
     * - never add ded/unded words as aliases and never infer dedication;
     * - generic collection words (mini, minis, miniature, minipet) are not identities;
     * - aliases are deliberately conservative where a tonic/weapon could conflict.
     *
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private function applyPhase7E2MiniatureAliases(array $items): array
    {
        static $cache = null;
        if ($cache !== null) {
            // Return the cached decorated catalog only when the source catalog has the same size.
            // Catalog items are immutable after construction in LittyWatch V5.2.
            if (count($cache) === count($items)) return $cache;
        }

        $aliasesByCanonical = [
            'miniature dhuum' => [
                'miniature dhuum', 'mini dhuum', 'mini duum', 'dhuum',
            ],
            'miniature flame djinn' => [
                'miniature flame djinn', 'miniature flame djin', 'mini flame djinn',
                'mini flame djin', 'flame djinn', 'flame djin',
            ],
            'miniature water djinn' => [
                'miniature water djinn', 'miniature water djin', 'mini water djinn',
                'mini water djin', 'water djinn', 'water djin',
            ],
            'miniature king adelbern' => [
                'miniature king adelbern', 'mini king adelbern', 'king adelbern', 'adelbern',
            ],
            'miniature lich' => [
                'miniature lich', 'mini lich', 'lich',
            ],
            'miniature shiro' => [
                'miniature shiro', 'mini shiro',
            ],
            "miniature shiro'ken assassin" => [
                "miniature shiro'ken assassin", 'miniature shiroken assassin',
                "mini shiro'ken assassin", 'mini shiroken assassin', 'shiroken assassin',
            ],
            'miniature ghostly hero' => [
                'miniature ghostly hero', 'mini ghostly hero', 'ghostly hero', 'ghero',
            ],
            'miniature kuunavang' => [
                'miniature kuunavang', 'mini kuunavang', 'kuunavang', 'kuuna', 'kuun',
            ],
            'miniature rift warden' => [
                'miniature rift warden', 'mini rift warden', 'rift warden', 'rift war',
            ],
            'miniature undead prince rurik' => [
                'miniature undead prince rurik', 'mini undead prince rurik',
                'undead prince rurik', 'undead prince', 'zombie rurik', 'undead rurik',
            ],
            'miniature mallyx' => [
                'miniature mallyx', 'mini mallyx', 'mallyx',
            ],
            'miniature polar bear' => [
                'miniature polar bear', 'mini polar bear', 'polar bear', 'polar',
            ],
            'miniature smite crawler' => [
                'miniature smite crawler', 'mini smite crawler', 'smite crawler',
            ],
            'miniature forest griffon' => [
                'miniature forest griffon', 'mini forest griffon', 'mini forrest griffon',
                'forest griffon', 'forrest griffon',
            ],
            'miniature princess salma' => [
                'miniature princess salma', 'mini princess salma', 'princess salma', 'salma',
            ],
            'miniature m.o.x.' => [
                'miniature m.o.x.', 'miniature mox', 'mini m.o.x.', 'mini mox',
            ],
        ];

        foreach ($items as $index => $item) {
            $name = mb_strtolower(trim((string)($item['name'] ?? '')));
            if ($name === '') continue;

            // Some historical catalog rows use a shortened canonical display name.
            // Allow those known V5.2 names as targets without manufacturing a new item.
            $canonicalLookup = $name;
            if ($name === 'miniature undead prince') $canonicalLookup = 'miniature undead prince rurik';

            if (!isset($aliasesByCanonical[$canonicalLookup])) continue;

            $existing = is_array($item['aliases'] ?? null) ? $item['aliases'] : [];
            $merged = array_values(array_unique(array_filter(array_merge(
                $existing,
                $aliasesByCanonical[$canonicalLookup]
            ), static function ($alias): bool {
                if (!is_string($alias)) return false;
                $alias = trim($alias);
                if ($alias === '') return false;
                // Dedication is a variant, never an item identity alias.
                return !preg_match('/\b(?:ded|unded|dedicated|undedicated)\b/iu', $alias);
            })));

            $items[$index]['aliases'] = $merged;
        }

        $cache = $items;
        return $items;
    }

PHP;

$code = substr($code, 0, $insertPos) . $helper . substr($code, $insertPos);

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

echo "OK: LittyWatch V5.2 Phase 7E.2 Miniature Canonical Cleanup geïnstalleerd.\n";
echo "Backup: {$backupDir}\n";
echo "Dedication-policy: ONGEWIJZIGD (ded/unded wordt niet gegokt).\n";
echo "Draai nu:\n";
echo "  php -l app/Parser/Catalog.php\n";
echo "  php tools/maintenance/phase7e2/smoke-test.php\n";
