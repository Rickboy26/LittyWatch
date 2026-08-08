<?php
declare(strict_types=1);

/**
 * LittyWatch - Armbrace of Truth manual image override installer
 *
 * Usage from project root:
 *   php tools/maintenance/install-armbrace-manual-override.php
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
$end   = '// LITTYWATCH_ARM_BRACE_MANUAL_OVERRIDE_END';

if (str_contains($contents, $begin)) {
    echo "Armbrace manual override staat al in item-image.php.\n";
    exit(0);
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden aangemaakt: {$backupDir}\n");
    exit(1);
}

$backup = $backupDir . '/item-image.php.before-armbrace-override.' . date('Ymd-His') . '.bak';
if (!copy($target, $backup)) {
    fwrite(STDERR, "ERROR: backup kon niet worden gemaakt.\n");
    exit(1);
}

$override = <<<'PHP'
<?php
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
?>

PHP;

if (file_put_contents($target, $override . $contents) === false) {
    @copy($backup, $target);
    fwrite(STDERR, "ERROR: patch schrijven mislukt; backup is teruggezet.\n");
    exit(1);
}

echo "OK: correcte Armbrace of Truth manual override geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Icon: assets/game-items/manual/armbrace-of-truth.png\n";
echo "Test: /item-image.php?item=Armbrace%20of%20Truth&size=72&v=manual1\n";
