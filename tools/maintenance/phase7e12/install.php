<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$writer = $root . '/app/Market/StructuredOfferWriter.php';

if (!is_file($writer)) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php ontbreekt.\n");
    exit(1);
}

$backup = $root . '/storage/backups/phase7e12-' . date('Ymd-His');
@mkdir($backup, 0775, true);
copy($writer, $backup . '/StructuredOfferWriter.php');

$src = __DIR__ . '/../../../app/Market/Phase7E12ResidualGuard.php';
$dst = $root . '/app/Market/Phase7E12ResidualGuard.php';
if (!is_file($src)) {
    fwrite(STDERR, "ERROR: Phase7E12ResidualGuard.php ontbreekt in pakket.\n");
    exit(1);
}
copy($src, $dst);

$code = file_get_contents($writer);
if ($code === false) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php kon niet worden gelezen.\n");
    exit(1);
}

if (!str_contains($code, 'LITTYWATCH_PHASE7E12_PREINSERT_RESIDUAL')) {
    $accepted = "if(\$r['quality_status']==='accepted'){";
    $pos = strpos($code, $accepted);

    if ($pos === false) {
        fwrite(STDERR, "ERROR: accepted branch niet gevonden in StructuredOfferWriter.\n");
        exit(1);
    }

    $block = <<<'PHP'
     // LITTYWATCH_PHASE7E12_PREINSERT_RESIDUAL
     $r=(new Phase7E12ResidualGuard($this->pdo))->repair($r);

PHP;

    $code = substr($code, 0, $pos) . $block . substr($code, $pos);

    if (file_put_contents($writer, $code) === false) {
        fwrite(STDERR, "ERROR: StructuredOfferWriter.php schrijven mislukt.\n");
        exit(1);
    }
}

echo "OK: LittyWatch V5.2 Phase 7E.12 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Fixes:\n";
echo "  - alc stack(s) / 1pt alc / 1point alch => Alcohol Point\n";
echo "  - unided gold => Unidentified Gold\n";
echo "  - No idea => service_or_noise\n";
echo "  - Dragon Staff context blokkeert false Miniature Celestial Dragon\n";
echo "  - Eggs Slice of => segmentation noise reject\n";
echo "  - Elite Tome policy ongewijzigd\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E12ResidualGuard.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e12/smoke-test.php\n";
