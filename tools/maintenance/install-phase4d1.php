<?php
declare(strict_types=1);

/**
 * LittyWatch V5.2 — Phase 4D.1 FIX1
 *
 * Main fix: synchronize Parser\Catalog into kb_items/kb_aliases so
 * CatalogFirstResolver/StrictCatalogGate use the same identities.
 *
 * This FIX1 removes the fragile Phase-4D points-return anchor that caused
 * installation failure when the same return line appeared twice.
 */

$root = dirname(__DIR__, 2);
$catalogFile = $root . '/app/Parser/Catalog.php';
$identityFile = $root . '/app/Market/CanonicalMarketIdentity.php';

foreach ([$catalogFile, $identityFile] as $required) {
    if (!is_file($required)) {
        fwrite(STDERR, "ERROR: vereist bestand ontbreekt: {$required}\n");
        exit(1);
    }
}

$stamp = date('Ymd-His');
$backupDir = $root . '/storage/backups/phase4d1-' . $stamp;
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden gemaakt: {$backupDir}\n");
    exit(1);
}
foreach ([$catalogFile, $identityFile] as $file) {
    if (!copy($file, $backupDir . '/' . basename($file))) {
        fwrite(STDERR, "ERROR: backup mislukt: {$file}\n");
        exit(1);
    }
}

function replace_once_4d1(string $contents, string $needle, string $replacement, string $label): string
{
    $count = substr_count($contents, $needle);
    if ($count !== 1) {
        throw new RuntimeException("Anchor {$label} verwacht 1x, gevonden {$count}x.");
    }
    return str_replace($needle, $replacement, $contents);
}

function write_checked_4d1(string $file, string $contents): void
{
    if (file_put_contents($file, $contents) === false) {
        throw new RuntimeException("Kon {$file} niet schrijven.");
    }
}

try {
    // --------------------------------------------------------------
    // 1) Canonical correction for legacy Undead Prince naming.
    // --------------------------------------------------------------
    $identity = (string)file_get_contents($identityFile);

    if (!str_contains($identity, 'LITTYWATCH_PHASE4D1_UNDEAD_PRINCE')) {
        $byKeyAnchor = "        'mad-kings-guard' => \"Miniature Mad King's Guard\",\n";
        $identity = replace_once_4d1(
            $identity,
            $byKeyAnchor,
            $byKeyAnchor
            . "        // LITTYWATCH_PHASE4D1_UNDEAD_PRINCE\n"
            . "        'mini-undead-prince-rurik' => 'Miniature Undead Prince',\n",
            'CanonicalMarketIdentity BY_KEY'
        );

        $legacyAnchor = "        'mad kings guard' => \"Miniature Mad King's Guard\",\n";
        $identity = replace_once_4d1(
            $identity,
            $legacyAnchor,
            $legacyAnchor
            . "        'miniature undead prince rurik' => 'Miniature Undead Prince',\n"
            . "        'undead prince rurik' => 'Miniature Undead Prince',\n",
            'CanonicalMarketIdentity LEGACY_NAMES'
        );

        write_checked_4d1($identityFile, $identity);
    }

    // --------------------------------------------------------------
    // 2) Synchronize fully merged parser catalog into KB tables.
    // --------------------------------------------------------------
    $catalog = (string)file_get_contents($catalogFile);

    if (!str_contains($catalog, 'LITTYWATCH_PHASE4D1_KB_CATALOG_SYNC')) {
        $anchor =
            "            \$this->knowledgeBase = new \\LittyWatch\\Knowledge\\KnowledgeBase(\$db);\n"
          . "            \$dbItems = \$this->knowledgeBase->allItems();\n";

        $replacement =
            "            \$this->knowledgeBase = new \\LittyWatch\\Knowledge\\KnowledgeBase(\$db);\n"
          . "            // LITTYWATCH_PHASE4D1_KB_CATALOG_SYNC\n"
          . "            // CatalogFirstResolver and StrictCatalogGate read kb_items/kb_aliases\n"
          . "            // directly. Keep those tables aligned with the merged parser catalog.\n"
          . "            \$syncByName = [];\n"
          . "            foreach (\$this->items as \$catalogItem) {\n"
          . "                if (!is_array(\$catalogItem)) continue;\n"
          . "                \$catalogKey = trim((string)(\$catalogItem['key'] ?? ''));\n"
          . "                \$rawName = trim((string)(\$catalogItem['name'] ?? ''));\n"
          . "                if (\$catalogKey === '' || \$rawName === '') continue;\n"
          . "                \$canonicalName = \\LittyWatch\\Market\\CanonicalMarketIdentity::nameFor(\$rawName, \$catalogKey);\n"
          . "                \$nameNorm = \\LittyWatch\\Knowledge\\KnowledgeBase::normalize(\$canonicalName);\n"
          . "                if (\$nameNorm === '') continue;\n"
          . "                \$aliases = array_values(array_unique(array_filter(array_map(\n"
          . "                    static fn(mixed \$a): string => trim((string)\$a),\n"
          . "                    array_merge([\$rawName, \$canonicalName], \$catalogItem['aliases'] ?? [])\n"
          . "                ))));\n"
          . "                if (!isset(\$syncByName[\$nameNorm])) {\n"
          . "                    \$syncByName[\$nameNorm] = [\n"
          . "                        'key'=>\$catalogKey,\n"
          . "                        'name'=>\$canonicalName,\n"
          . "                        'category'=>(string)(\$catalogItem['category'] ?? 'unknown'),\n"
          . "                        'aliases'=>\$aliases,\n"
          . "                    ];\n"
          . "                } else {\n"
          . "                    \$syncByName[\$nameNorm]['aliases'] = array_values(array_unique(array_merge(\n"
          . "                        \$syncByName[\$nameNorm]['aliases'], \$aliases\n"
          . "                    )));\n"
          . "                }\n"
          . "            }\n"
          . "            \$syncItem = \$db->prepare(\"INSERT INTO kb_items(key,name,category_key,source,source_id,metadata_json,active,updated_at) VALUES(:key,:name,:category,'parser_catalog',NULL,'{}',1,:updated) ON CONFLICT(key) DO UPDATE SET name=excluded.name, category_key=CASE WHEN kb_items.category_key='' OR kb_items.category_key='unknown' THEN excluded.category_key ELSE kb_items.category_key END, active=1, updated_at=excluded.updated_at\");\n"
          . "            \$syncAlias = \$db->prepare(\"INSERT OR IGNORE INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES(:item_key,:alias,:normalized,'parser_catalog')\");\n"
          . "            foreach (\$syncByName as \$syncItemRow) {\n"
          . "                \$syncItem->execute([\n"
          . "                    ':key'=>\$syncItemRow['key'], ':name'=>\$syncItemRow['name'],\n"
          . "                    ':category'=>\$syncItemRow['category'], ':updated'=>gmdate('c'),\n"
          . "                ]);\n"
          . "                foreach (\$syncItemRow['aliases'] as \$syncAliasValue) {\n"
          . "                    \$normalizedAlias = \\LittyWatch\\Knowledge\\KnowledgeBase::normalize((string)\$syncAliasValue);\n"
          . "                    if (\$normalizedAlias === '') continue;\n"
          . "                    \$syncAlias->execute([\n"
          . "                        ':item_key'=>\$syncItemRow['key'], ':alias'=>(string)\$syncAliasValue,\n"
          . "                        ':normalized'=>\$normalizedAlias,\n"
          . "                    ]);\n"
          . "                }\n"
          . "            }\n"
          . "            \$dbItems = \$this->knowledgeBase->allItems();\n";

        $catalog = replace_once_4d1($catalog, $anchor, $replacement, 'Catalog KB initialization');
        write_checked_4d1($catalogFile, $catalog);
    }

    // Syntax checks before bootstrapping.
    foreach ([$catalogFile, $identityFile] as $lintFile) {
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($lintFile) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            throw new RuntimeException("PHP syntaxcheck faalde voor {$lintFile}:\n" . implode("\n", $out));
        }
    }

    require $root . '/bootstrap.php';
    $db = db();

    // Force one Catalog construction so the KB sync runs immediately.
    new \LittyWatch\Parser\Catalog($root . '/app/Data', $db);

    $kbItems = (int)$db->query("SELECT COUNT(*) FROM kb_items WHERE active=1")->fetchColumn();
    $kbAliases = (int)$db->query("SELECT COUNT(*) FROM kb_aliases")->fetchColumn();

    $checks = [
        "Zaishen Key",
        "Miniature Ghostly Hero",
        "Miniature Undead Prince",
        "Ghozer's Key",
    ];
    $stmt = $db->prepare("SELECT key,name FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");

    echo "OK: LittyWatch V5.2 Phase 4D.1 FIX1 geïnstalleerd.\n";
    echo "Backup: {$backupDir}\n";
    echo "Actieve KB items: {$kbItems}\n";
    echo "KB aliases: {$kbAliases}\n";
    echo "Catalog checks:\n";
    foreach ($checks as $name) {
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  {$name}: " . ($row ? "OK [".$row['key']."]" : "NIET GEVONDEN") . "\n";
    }

    echo "\nDraai daarna de volledige reparse opnieuw.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Rollback code vanuit {$backupDir}...\n");
    foreach ([$catalogFile, $identityFile] as $file) {
        $backup = $backupDir . '/' . basename($file);
        if (is_file($backup)) @copy($backup, $file);
    }
    exit(1);
}
