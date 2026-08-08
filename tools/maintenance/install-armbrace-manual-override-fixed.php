<?php
declare(strict_types=1);

/**
 * LittyWatch - Armbrace of Truth manual image override installer (fixed)
 *
 * Installs the override INSIDE the existing item-image.php PHP block,
 * directly after declare(strict_types=1); so strict_types remains the
 * first statement.
 */

$root = dirname(__DIR__, 2);
$target = $root . '/item-image.php';
$icon = $root . '/assets/game-items/manual/armbrace-of-truth.png';
$backupDir = $root . '/storage/backups';

if (!is_file($target)) {
    fwrite(STDERR, "ERROR: item-image.php niet gevonden: {$target}\n");
    exit(1);
}

if (!is_file($icon)) {
    fwrite(STDERR, "ERROR: Armbrace icon niet gevonden: {$icon}\n");
    exit(1);
}

$contents = file_get_contents($target);
if ($contents === false) {
    fwrite(STDERR, "ERROR: item-image.php kon niet worden gelezen.\n");
    exit(1);
}

$begin = '// LITTYWATCH_ARM_BRACE_MANUAL_OVERRIDE_BEGIN';
if (str_contains($contents, $begin)) {
    echo "Armbrace manual override staat al in item-image.php.\n";
    exit(0);
}

$needle = 'declare(strict_types=1);';
$pos = strpos($contents, $needle);
if ($pos === false) {
    fwrite(STDERR, "ERROR: declare(strict_types=1); niet gevonden in item-image.php.\n");
    exit(1);
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden aangemaakt: {$backupDir}\n");
    exit(1);
}

$backup = $backupDir . '/item-image.php.before-armbrace-override-fixed.' . date('Ymd-His') . '.bak';
if (!copy($target, $backup)) {
    fwrite(STDERR, "ERROR: backup kon niet worden gemaakt.\n");
    exit(1);
}

$override = <<<'PHP'

// LITTYWATCH_ARM_BRACE_MANUAL_OVERRIDE_BEGIN
// Handmatige hoogste-prioriteit override voor het correcte Armbrace of Truth inventory icon.
if (
    isset($_GET['item'])
    && strcasecmp(trim((string) $_GET['item']), 'Armbrace of Truth') === 0
) {
    $manualArmbrace = __DIR__ . '/assets/game-items/manual/armbrace-of-truth.png';

    if (is_file($manualArmbrace)) {
        header('Content-Type: image/png');
        header('Content-Length: ' . (string) filesize($manualArmbrace));
        header('Cache-Control: public, max-age=3600');
        header('X-LittyWatch-Image-Source: manual-override');
        readfile($manualArmbrace);
        exit;
    }
}
// LITTYWATCH_ARM_BRACE_MANUAL_OVERRIDE_END
PHP;

$insertAt = $pos + strlen($needle);
$new = substr($contents, 0, $insertAt) . $override . substr($contents, $insertAt);

if (file_put_contents($target, $new) === false) {
    @copy($backup, $target);
    fwrite(STDERR, "ERROR: patch schrijven mislukt; backup is teruggezet.\n");
    exit(1);
}

exec('php -l ' . escapeshellarg($target), $lintOut, $lintCode);
if ($lintCode !== 0) {
    @copy($backup, $target);
    fwrite(STDERR, "ERROR: item-image.php faalde syntaxcheck; backup is teruggezet.\n");
    fwrite(STDERR, implode("\n", $lintOut) . "\n");
    exit(1);
}

echo "OK: correcte Armbrace of Truth manual override geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Icon: assets/game-items/manual/armbrace-of-truth.png\n";
echo "Test: /item-image.php?item=Armbrace%20of%20Truth&size=72&v=manual-fixed\n";
