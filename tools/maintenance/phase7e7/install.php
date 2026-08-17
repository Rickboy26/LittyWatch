<?php
declare(strict_types=1);

/**
 * LittyWatch V5.2 - Phase 7E.7
 * Shiro'ken Canonical KB Dedup
 *
 * Canonical key:
 *   miniature-shiro-ken-assassin
 *
 * Legacy duplicate:
 *   miniature-shiroken-assassin
 */

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$catalogFile = $root . '/app/Data/phase4f-items.json';
$canonicalKey = 'miniature-shiro-ken-assassin';
$legacyKey = 'miniature-shiroken-assassin';
$canonicalName = "Miniature Shiro'ken Assassin";

if (!is_file($catalogFile)) {
    fwrite(STDERR, "ERROR: phase4f-items.json ontbreekt: {$catalogFile}\n");
    exit(1);
}

$backupDir = $root . '/storage/backups/phase7e7-' . date('Ymd-His');
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden aangemaakt.\n");
    exit(1);
}
if (!copy($catalogFile, $backupDir . '/phase4f-items.json')) {
    fwrite(STDERR, "ERROR: backup van phase4f-items.json mislukt.\n");
    exit(1);
}

$raw = file_get_contents($catalogFile);
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "ERROR: phase4f-items.json is geen geldige JSON.\n");
    exit(1);
}

$changed = false;
$canonicalIndex = null;
$legacyIndex = null;

foreach ($data as $i => $row) {
    if (!is_array($row)) continue;
    $key = (string)($row['key'] ?? '');
    if ($key === $canonicalKey) $canonicalIndex = $i;
    if ($key === $legacyKey) $legacyIndex = $i;
}

/*
 * The old Phase 4F record used miniature-shiroken-assassin. If no canonical
 * record exists yet, rename that record. If both exist, merge aliases and
 * remove the duplicate.
 */
if ($canonicalIndex === null && $legacyIndex !== null) {
    $data[$legacyIndex]['key'] = $canonicalKey;
    $data[$legacyIndex]['name'] = $canonicalName;
    $canonicalIndex = $legacyIndex;
    $legacyIndex = null;
    $changed = true;
}

if ($canonicalIndex === null) {
    fwrite(STDERR, "ERROR: geen Shiro'ken Assassin record gevonden in phase4f-items.json.\n");
    exit(1);
}

$aliases = $data[$canonicalIndex]['aliases'] ?? [];
if (!is_array($aliases)) $aliases = [];

if ($legacyIndex !== null && $legacyIndex !== $canonicalIndex) {
    $legacyAliases = $data[$legacyIndex]['aliases'] ?? [];
    if (is_array($legacyAliases)) $aliases = array_merge($aliases, $legacyAliases);
    unset($data[$legacyIndex]);
    $data = array_values($data);

    // Re-find canonical index after array compaction.
    foreach ($data as $i => $row) {
        if (($row['key'] ?? null) === $canonicalKey) {
            $canonicalIndex = $i;
            break;
        }
    }
    $changed = true;
}

$aliases[] = $canonicalName;
$aliases[] = "Shiro'ken Assassin mini";
$aliases[] = 'Shiroken Assassin mini';
$aliases[] = "Miniature Shiro'ken Assassin";
$aliases[] = 'Miniature Shiroken Assassin';
$aliases[] = "Shiro'ken Assassin";
$aliases[] = 'Shiroken Assassin';

$aliases = array_values(array_unique(array_filter(array_map(
    static fn($v) => trim((string)$v),
    $aliases
), static fn($v) => $v !== '')));

$data[$canonicalIndex]['key'] = $canonicalKey;
$data[$canonicalIndex]['name'] = $canonicalName;
$data[$canonicalIndex]['category'] = $data[$canonicalIndex]['category'] ?? 'miniatures';
$data[$canonicalIndex]['aliases'] = $aliases;

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false || file_put_contents($catalogFile, $json . PHP_EOL) === false) {
    fwrite(STDERR, "ERROR: phase4f-items.json schrijven mislukt.\n");
    exit(1);
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->beginTransaction();
try {
    // Detect actual KB schema instead of assuming optional columns.
    $columns = [];
    foreach ($pdo->query("PRAGMA table_info(kb_items)") as $r) {
        $columns[(string)$r['name']] = true;
    }
    if (!isset($columns['key'])) {
        throw new RuntimeException('kb_items.key ontbreekt.');
    }

    $aliasColumns = [];
    foreach ($pdo->query("PRAGMA table_info(kb_aliases)") as $r) {
        $aliasColumns[(string)$r['name']] = true;
    }

    // If both KB identities exist, migrate aliases that point at the legacy key.
    if (isset($aliasColumns['item_key'])) {
        $st = $pdo->prepare("UPDATE kb_aliases SET item_key=:canonical WHERE item_key=:legacy");
        $st->execute([':canonical'=>$canonicalKey, ':legacy'=>$legacyKey]);
    } elseif (isset($aliasColumns['key'])) {
        // Some older KB layouts used `key` as the item reference.
        $st = $pdo->prepare("UPDATE kb_aliases SET key=:canonical WHERE key=:legacy");
        $st->execute([':canonical'=>$canonicalKey, ':legacy'=>$legacyKey]);
    }

    // Remove duplicate aliases if migration caused collisions.
    if (isset($aliasColumns['id']) && isset($aliasColumns['alias'])) {
        $ref = isset($aliasColumns['item_key']) ? 'item_key' : (isset($aliasColumns['key']) ? 'key' : null);
        if ($ref !== null) {
            $pdo->exec(
                "DELETE FROM kb_aliases
                 WHERE id NOT IN (
                    SELECT MIN(id) FROM kb_aliases
                    GROUP BY {$ref}, lower(trim(alias))
                 )"
            );
        }
    }

    // Remove the obsolete KB item only. Canonical KB data remains untouched.
    $st = $pdo->prepare("DELETE FROM kb_items WHERE key=:legacy");
    $st->execute([':legacy'=>$legacyKey]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR: KB-dedup mislukt: " . $e->getMessage() . "\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.7 geïnstalleerd.\n";
echo "Canonical: {$canonicalKey}\n";
echo "Verwijderd legacy duplicate: {$legacyKey}\n";
echo "Backup: {$backupDir}\n";
echo "Draai nu:\n";
echo "  php -l app/Parser/Catalog.php\n";
echo "  php tools/maintenance/phase7e7/smoke-test.php\n";
