<?php
declare(strict_types=1);

/**
 * LittyWatch V5.2 - Phase 7E.7 FIX2
 * Shiro'ken Canonical KB Dedup
 *
 * Fixes UNIQUE constraint collisions in kb_aliases by migrating aliases
 * conflict-safe instead of mass-updating item_key.
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

$backupDir = $root . '/storage/backups/phase7e7-fix2-' . date('Ymd-His');
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden aangemaakt.\n");
    exit(1);
}
copy($catalogFile, $backupDir . '/phase4f-items.json');

$raw = file_get_contents($catalogFile);
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "ERROR: phase4f-items.json is geen geldige JSON.\n");
    exit(1);
}

$canonicalIndex = null;
$legacyIndex = null;
foreach ($data as $i => $row) {
    if (!is_array($row)) continue;
    $key = (string)($row['key'] ?? '');
    if ($key === $canonicalKey) $canonicalIndex = $i;
    if ($key === $legacyKey) $legacyIndex = $i;
}

if ($canonicalIndex === null && $legacyIndex !== null) {
    $data[$legacyIndex]['key'] = $canonicalKey;
    $data[$legacyIndex]['name'] = $canonicalName;
    $canonicalIndex = $legacyIndex;
    $legacyIndex = null;
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

    foreach ($data as $i => $row) {
        if (($row['key'] ?? null) === $canonicalKey) {
            $canonicalIndex = $i;
            break;
        }
    }
}

$aliases = array_merge($aliases, [
    $canonicalName,
    "Shiro'ken Assassin mini",
    'Shiroken Assassin mini',
    "Miniature Shiro'ken Assassin",
    'Miniature Shiroken Assassin',
    "Shiro'ken Assassin",
    'Shiroken Assassin',
]);

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

function tableColumns(PDO $pdo, string $table): array {
    $out = [];
    foreach ($pdo->query("PRAGMA table_info(" . $table . ")") as $r) {
        $out[(string)$r['name']] = true;
    }
    return $out;
}

$kbCols = tableColumns($pdo, 'kb_items');
$aliasCols = tableColumns($pdo, 'kb_aliases');

if (!isset($kbCols['key'])) {
    fwrite(STDERR, "ERROR: kb_items.key ontbreekt.\n");
    exit(1);
}

$refCol = isset($aliasCols['item_key']) ? 'item_key' : (isset($aliasCols['key']) ? 'key' : null);
if ($refCol === null) {
    fwrite(STDERR, "ERROR: geen item-key kolom gevonden in kb_aliases.\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    // Backup affected DB rows as SQL-ish text for inspection/recovery.
    $dump = [];
    $st = $pdo->prepare("SELECT * FROM kb_items WHERE key IN (:c,:l)");
    // SQLite cannot reuse named placeholders reliably in all builds, so use positional below.
    $st = $pdo->prepare("SELECT * FROM kb_items WHERE key IN (?, ?)");
    $st->execute([$canonicalKey, $legacyKey]);
    $dump['kb_items'] = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("SELECT * FROM kb_aliases WHERE {$refCol} IN (?, ?)");
    $st->execute([$canonicalKey, $legacyKey]);
    $dump['kb_aliases'] = $st->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents(
        $backupDir . '/kb-shiroken-before.json',
        json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
    );

    /*
     * Conflict-safe alias migration.
     * The previous installer did:
     *   UPDATE kb_aliases SET item_key=canonical WHERE item_key=legacy
     * which can violate UNIQUE(item_key, normalized_alias).
     *
     * FIX2:
     * - for each legacy alias, if canonical already has the same normalized_alias,
     *   delete the legacy duplicate;
     * - otherwise move just that row to canonical.
     */
    $select = $pdo->prepare("SELECT * FROM kb_aliases WHERE {$refCol} = ?");
    $select->execute([$legacyKey]);
    $legacyAliases = $select->fetchAll(PDO::FETCH_ASSOC);

    $moved = 0;
    $deduped = 0;

    foreach ($legacyAliases as $row) {
        $whereParts = [];
        $params = [$canonicalKey];

        if (isset($aliasCols['normalized_alias']) && array_key_exists('normalized_alias', $row)) {
            $whereParts[] = "normalized_alias = ?";
            $params[] = $row['normalized_alias'];
        } elseif (isset($aliasCols['alias']) && array_key_exists('alias', $row)) {
            $whereParts[] = "lower(trim(alias)) = lower(trim(?))";
            $params[] = $row['alias'];
        } else {
            throw new RuntimeException('kb_aliases heeft geen normalized_alias/alias kolom voor veilige dedup.');
        }

        $existsSql = "SELECT 1 FROM kb_aliases WHERE {$refCol} = ? AND " . implode(' AND ', $whereParts) . " LIMIT 1";
        $exists = $pdo->prepare($existsSql);
        $exists->execute($params);
        $duplicateExists = (bool)$exists->fetchColumn();

        if ($duplicateExists) {
            if (isset($aliasCols['id']) && isset($row['id'])) {
                $del = $pdo->prepare("DELETE FROM kb_aliases WHERE id = ?");
                $del->execute([$row['id']]);
            } else {
                $delParams = [$legacyKey];
                $delWhere = [];
                if (isset($aliasCols['normalized_alias']) && array_key_exists('normalized_alias', $row)) {
                    $delWhere[] = "normalized_alias = ?";
                    $delParams[] = $row['normalized_alias'];
                } else {
                    $delWhere[] = "lower(trim(alias)) = lower(trim(?))";
                    $delParams[] = $row['alias'];
                }
                $del = $pdo->prepare("DELETE FROM kb_aliases WHERE {$refCol} = ? AND " . implode(' AND ', $delWhere));
                $del->execute($delParams);
            }
            $deduped++;
        } else {
            if (isset($aliasCols['id']) && isset($row['id'])) {
                $upd = $pdo->prepare("UPDATE kb_aliases SET {$refCol} = ? WHERE id = ?");
                $upd->execute([$canonicalKey, $row['id']]);
            } else {
                if (isset($aliasCols['normalized_alias']) && array_key_exists('normalized_alias', $row)) {
                    $upd = $pdo->prepare("UPDATE kb_aliases SET {$refCol} = ? WHERE {$refCol} = ? AND normalized_alias = ?");
                    $upd->execute([$canonicalKey, $legacyKey, $row['normalized_alias']]);
                } else {
                    $upd = $pdo->prepare("UPDATE kb_aliases SET {$refCol} = ? WHERE {$refCol} = ? AND lower(trim(alias)) = lower(trim(?))");
                    $upd->execute([$canonicalKey, $legacyKey, $row['alias']]);
                }
            }
            $moved++;
        }
    }

    // Safety: no aliases may still reference legacy key.
    $st = $pdo->prepare("SELECT COUNT(*) FROM kb_aliases WHERE {$refCol} = ?");
    $st->execute([$legacyKey]);
    $remainingLegacyAliases = (int)$st->fetchColumn();
    if ($remainingLegacyAliases !== 0) {
        throw new RuntimeException("er verwijzen nog {$remainingLegacyAliases} aliases naar legacy key.");
    }

    $st = $pdo->prepare("DELETE FROM kb_items WHERE key = ?");
    $st->execute([$legacyKey]);
    $deletedItems = $st->rowCount();

    $pdo->commit();

    echo "OK: LittyWatch V5.2 Phase 7E.7 FIX2 geïnstalleerd.\n";
    echo "Canonical: {$canonicalKey}\n";
    echo "Legacy aliases gemigreerd: {$moved}\n";
    echo "Dubbele aliases verwijderd: {$deduped}\n";
    echo "Legacy kb_items verwijderd: {$deletedItems}\n";
    echo "Backup: {$backupDir}\n";
    echo "Draai nu:\n";
    echo "  php -l app/Parser/Catalog.php\n";
    echo "  php tools/maintenance/phase7e7/smoke-test.php\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR: KB-dedup mislukt: " . $e->getMessage() . "\n");
    exit(1);
}
