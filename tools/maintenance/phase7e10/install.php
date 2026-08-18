<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$semantic = $root . '/app/Parser/SemanticNormalizer.php';
$writer = $root . '/app/Market/StructuredOfferWriter.php';

foreach ([$semantic,$writer] as $f) {
    if (!is_file($f)) {
        fwrite(STDERR,"ERROR: ontbreekt: {$f}\n");
        exit(1);
    }
}

$backup = $root . '/storage/backups/phase7e10-' . date('Ymd-His');
@mkdir($backup,0775,true);
copy($semantic,$backup.'/SemanticNormalizer.php');
copy($writer,$backup.'/StructuredOfferWriter.php');

$src = __DIR__ . '/../../../app/Market/Phase7E10ResidualGuard.php';
$dst = $root . '/app/Market/Phase7E10ResidualGuard.php';
if (!is_file($src)) {
    fwrite(STDERR,"ERROR: Phase7E10ResidualGuard.php ontbreekt in pakket.\n");
    exit(1);
}
copy($src,$dst);

// Targeted typo only in observed Celestial/Zodiac staff context.
$code = file_get_contents($semantic);
if (!str_contains($code,'LITTYWATCH_PHASE7E10_CELESTAL_STAFF')) {
    $marker = 'LITTYWATCH_PHASE7E9_REGULAR_TOME_LIST';
    $p = strpos($code,$marker);
    if ($p === false) {
        fwrite(STDERR,"ERROR: 7E.9 marker niet gevonden in SemanticNormalizer.\n");
        exit(1);
    }
    $line = strpos($code,"\n",$p);
    $block = <<<'PHP'

        // LITTYWATCH_PHASE7E10_CELESTAL_STAFF
        // Observed typo: "Celestal/Zodiac Staff ..." => Celestial Staff.
        $text = preg_replace(
            '/\bcelestal\b(?=\s*\/\s*zodiac(?:[_\s-]*staff)?\b)/iu',
            'Celestial Staff',
            $text
        ) ?? $text;
PHP;
    $code = substr($code,0,$line+1).$block.substr($code,$line+1);
    file_put_contents($semantic,$code);
}

// Run residual guard for every row before accepted-only gate.
$code = file_get_contents($writer);
if (!str_contains($code,'LITTYWATCH_PHASE7E10_PREINSERT_RESIDUAL')) {
    $accepted = "if(\$r['quality_status']==='accepted'){";
    $p = strpos($code,$accepted);
    if ($p === false) {
        fwrite(STDERR,"ERROR: accepted branch niet gevonden in StructuredOfferWriter.\n");
        exit(1);
    }
    $block = <<<'PHP'
     // LITTYWATCH_PHASE7E10_PREINSERT_RESIDUAL
     $r=(new Phase7E10ResidualGuard($this->pdo))->repair($r);

PHP;
    $code = substr($code,0,$p).$block.substr($code,$p);
    file_put_contents($writer,$code);
}

echo "OK: LittyWatch V5.2 Phase 7E.10 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E10ResidualGuard.php\n";
echo "  php -l app/Parser/SemanticNormalizer.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e10/smoke-test.php\n";
