<?php
declare(strict_types=1);

$root = dirname(__DIR__,3);
$catalogFile = $root.'/app/Parser/Catalog.php';
$matcherFile = $root.'/app/Parser/ItemMatcher.php';

foreach ([$catalogFile,$matcherFile] as $f) {
    if (!is_file($f)) {
        fwrite(STDERR, "ERROR: {$f} bestaat niet.\n");
        exit(1);
    }
}

$backupDir = $root.'/storage/backups/phase7a-'.date('Ymd-His');
if (!is_dir($backupDir) && !mkdir($backupDir,0775,true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden gemaakt.\n");
    exit(1);
}

copy($catalogFile,$backupDir.'/Catalog.php');
copy($matcherFile,$backupDir.'/ItemMatcher.php');

$catalog = file_get_contents($catalogFile);
$matcher = file_get_contents($matcherFile);

if ($catalog === false || $matcher === false) {
    fwrite(STDERR, "ERROR: parserbestanden konden niet worden gelezen.\n");
    exit(1);
}

if (str_contains($catalog,'LITTYWATCH_PHASE7A_LEARNED_ALIASES')) {
    echo "Phase 7A lijkt al geïnstalleerd.\n";
    echo "Backup: {$backupDir}\n";
    exit(0);
}

$catalogAnchor = "                \$this->items = \$this->mergeItems(\$this->items, \$mapped);\n";
$catalogCount = substr_count($catalog,$catalogAnchor);
if ($catalogCount !== 1) {
    fwrite(STDERR,"ERROR: Catalog anchor verwacht 1x, gevonden {$catalogCount}x.\n");
    exit(1);
}

$catalogPatch = $catalogAnchor . <<<'PHP'

                // LITTYWATCH_PHASE7A_LEARNED_ALIASES
                try {
                    $learnedStmt = $db->query("
                        SELECT normalized_alias, alias, item_key, item_name, confidence
                        FROM parser_learned_aliases
                        WHERE active = 1
                          AND confidence >= 0.99
                        ORDER BY confidence DESC, id
                    ");

                    $learnedByKey = [];
                    foreach ($learnedStmt as $learned) {
                        $alias = trim((string)($learned['alias'] ?? ''));
                        $normalized = trim((string)($learned['normalized_alias'] ?? ''));
                        $itemKey = trim((string)($learned['item_key'] ?? ''));

                        if ($alias === '' || $normalized === '' || $itemKey === '') continue;

                        $compact = preg_replace('/[^a-z0-9]+/iu', '', mb_strtolower($normalized)) ?? '';
                        if (mb_strlen($compact) < 4) continue;

                        $learnedByKey[$itemKey][] = $alias;
                    }

                    if ($learnedByKey !== []) {
                        foreach ($this->items as $idx => $catalogItem) {
                            $key = trim((string)($catalogItem['key'] ?? ''));
                            if ($key === '' || !isset($learnedByKey[$key])) continue;

                            $existing = $catalogItem['aliases'] ?? [];
                            if (!is_array($existing)) $existing = [];

                            $this->items[$idx]['aliases'] = array_values(array_unique(array_merge(
                                $existing,
                                $learnedByKey[$key]
                            )));
                        }
                    }
                } catch (\Throwable $e) {
                    // Parser blijft beschikbaar als de learning table ontbreekt.
                }
PHP;

$catalogNew = str_replace($catalogAnchor,$catalogPatch,$catalog);

$matcherAnchor = "            foreach (\$aliases as \$alias) {\n";
$matcherCount = substr_count($matcher,$matcherAnchor);
if ($matcherCount !== 1) {
    fwrite(STDERR,"ERROR: ItemMatcher alias-loop anchor verwacht 1x, gevonden {$matcherCount}x.\n");
    exit(1);
}

$matcherPatch = $matcherAnchor . <<<'PHP'
                // LITTYWATCH_PHASE7A_RUNTIME_GUARDS
                $aliasNormalized = mb_strtolower(trim((string)$alias));
                $aliasCompact = preg_replace('/[^a-z0-9]+/iu', '', $aliasNormalized) ?? '';
                if (mb_strlen($aliasCompact) < 4) continue;

                $itemName = mb_strtolower(trim((string)($item['name'] ?? '')));

                if (str_starts_with($itemName, 'miniature ')
                    && !preg_match('/\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu', $text)) {
                    continue;
                }

                if (in_array($itemName, ['elite tome','normal tome','tome'], true)) {
                    continue;
                }

PHP;

$matcherNew = str_replace($matcherAnchor,$matcherPatch,$matcher);

if (file_put_contents($catalogFile,$catalogNew) === false
    || file_put_contents($matcherFile,$matcherNew) === false) {
    copy($backupDir.'/Catalog.php',$catalogFile);
    copy($backupDir.'/ItemMatcher.php',$matcherFile);
    fwrite(STDERR,"ERROR: schrijven mislukt. Rollback uitgevoerd.\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7A geïnstalleerd.\n";
echo "Backup: {$backupDir}\n";
echo "Controleer nu:\n";
echo "  php -l app/Parser/Catalog.php\n";
echo "  php -l app/Parser/ItemMatcher.php\n";
echo "  php tools/maintenance/phase7a/smoke-test.php\n";
