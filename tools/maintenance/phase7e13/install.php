<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$pdo = db();
$writer = $root . '/app/Market/StructuredOfferWriter.php';

if (!is_file($writer)) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php ontbreekt.\n");
    exit(1);
}

$backup = $root . '/storage/backups/phase7e13-' . date('Ymd-His');
@mkdir($backup, 0775, true);
copy($writer, $backup . '/StructuredOfferWriter.php');

$src = __DIR__ . '/../../../app/Market/Phase7E13ResidualGuard.php';
$dst = $root . '/app/Market/Phase7E13ResidualGuard.php';
if (!is_file($src)) {
    fwrite(STDERR, "ERROR: Phase7E13ResidualGuard.php ontbreekt in pakket.\n");
    exit(1);
}
copy($src, $dst);

function ensureItem13(PDO $pdo, string $key, string $name, string $category): void {
    $st = $pdo->prepare("SELECT COUNT(*) FROM kb_items WHERE key=?");
    $st->execute([$key]);

    if ((int)$st->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO kb_items (key,name,category_key,source,source_id,metadata_json,active,updated_at) VALUES (?,?,?,?,?,?,1,?)")
            ->execute([$key,$name,$category,'phase7e13',null,'{"phase":"7E.13"}',date(DATE_ATOM)]);
    } else {
        $pdo->prepare("UPDATE kb_items SET name=?,active=1,updated_at=? WHERE key=?")
            ->execute([$name,date(DATE_ATOM),$key]);
    }
}

$pdo->beginTransaction();
try {
    ensureItem13($pdo, 'scythe-grip-of-the-necromancer', 'Scythe Grip of the Necromancer', 'weapon_upgrades');
    ensureItem13($pdo, 'icy-dragon-sword', 'Icy Dragon Sword', 'weapons');
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR: KB update mislukt: ".$e->getMessage()."\n");
    exit(1);
}

$code = file_get_contents($writer);
if ($code === false) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php lezen mislukt.\n");
    exit(1);
}

if (!str_contains($code, 'LITTYWATCH_PHASE7E13_PREINSERT_RESIDUAL')) {
    $accepted = "if(\$r['quality_status']==='accepted'){";
    $pos = strpos($code, $accepted);
    if ($pos === false) {
        fwrite(STDERR, "ERROR: accepted branch niet gevonden in StructuredOfferWriter.\n");
        exit(1);
    }

    $block = "     // LITTYWATCH_PHASE7E13_PREINSERT_RESIDUAL\n     \$r=(new Phase7E13ResidualGuard(\$this->pdo))->repair(\$r);\n\n";
    $code = substr($code, 0, $pos) . $block . substr($code, $pos);

    if (file_put_contents($writer, $code) === false) {
        fwrite(STDERR, "ERROR: StructuredOfferWriter.php schrijven mislukt.\n");
        exit(1);
    }
}

echo "OK: LittyWatch V5.2 Phase 7E.13 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Fixes:\n";
echo "  - Ghostly q/OS/HCT/attribute context blokkeert false miniature\n";
echo "  - Of the Necro +5 SR for scythe => Scythe Grip of the Necromancer\n";
echo "  - icedragon blade => Icy Dragon Sword\n";
echo "  - D-cakes => Birthday Cupcake\n";
echo "  - gold value / domination+illusion => modifier fragment reject\n";
echo "  - Heart of Shiverpeaks + DD / titels => service_or_noise\n";
echo "  - 40 40 d, Miniature Lich en Elite Tome policy ongewijzigd\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E13ResidualGuard.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e13/smoke-test.php\n";
