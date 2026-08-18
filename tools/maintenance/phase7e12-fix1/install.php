<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$backup = $root . '/storage/backups/phase7e12-fix1-' . date('Ymd-His');
@mkdir($backup, 0775, true);

$dbFile = null;
foreach ($pdo->query('PRAGMA database_list') as $r) {
    if (($r['name'] ?? '') === 'main') {
        $dbFile = (string)($r['file'] ?? '');
        break;
    }
}
if ($dbFile && is_file($dbFile)) {
    copy($dbFile, $backup . '/' . basename($dbFile));
}

function norm12f1(string $v): string {
    $v = mb_strtolower(trim(str_replace(['’','´','`'], "'", $v)));
    $v = preg_replace('/[^a-z0-9]+/u', ' ', $v) ?? $v;
    return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
}

$key = 'alcohol-point';
$name = 'Alcohol Point';
$now = date(DATE_ATOM);

$pdo->beginTransaction();
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM kb_items WHERE key=?");
    $st->execute([$key]);

    if ((int)$st->fetchColumn() === 0) {
        $pdo->prepare("
            INSERT INTO kb_items
                (key,name,category_key,source,source_id,metadata_json,active,updated_at)
            VALUES
                (?,?,?,?,?,?,1,?)
        ")->execute([
            $key,
            $name,
            'unknown',
            'phase7e12-fix1',
            null,
            '{"phase":"7E.12 FIX1","purpose":"canonical market identity for alcohol points"}',
            $now
        ]);
    } else {
        $pdo->prepare("
            UPDATE kb_items
            SET name=?, active=1, updated_at=?
            WHERE key=?
        ")->execute([$name,$now,$key]);
    }

    $aliases = [
        'Alcohol Point',
        'Alcohol Points',
        'alc stack',
        'alc stacks',
        '1pt alc',
        '1 pt alc',
        '1point alch',
        '1 point alch',
        '1point alc',
        '1 point alc',
    ];

    foreach ($aliases as $alias) {
        $norm = norm12f1($alias);
        $st = $pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");
        $st->execute([$norm]);
        $owner = $st->fetchColumn();

        if ($owner === false) {
            $pdo->prepare("
                INSERT INTO kb_aliases(item_key,alias,normalized_alias,source)
                VALUES(?,?,?,?)
            ")->execute([$key,$alias,$norm,'phase7e12-fix1']);
        } elseif ((string)$owner !== $key) {
            echo "SKIP alias-conflict: {$alias} -> {$owner}\n";
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR: Alcohol Point KB-installatie mislukt: ".$e->getMessage()."\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.12 FIX1 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Alcohol Point staat nu als actieve canonical KB identity geregistreerd.\n";
echo "Draai nu:\n";
echo "  php tools/maintenance/phase7e12-fix1/smoke-test.php\n";
