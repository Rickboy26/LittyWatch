<?php
declare(strict_types=1);

$root = dirname(__DIR__,3);
$file = $root.'/tools/maintenance/phase5e/dry-run.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: {$file} bestaat niet.\n");
    exit(1);
}

$backupDir = $root.'/storage/backups/phase5e-fix1-'.date('Ymd-His');
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden gemaakt.\n");
    exit(1);
}
copy($file, $backupDir.'/dry-run.php');

$code = file_get_contents($file);
if ($code === false) {
    fwrite(STDERR, "ERROR: dry-run.php kon niet worden gelezen.\n");
    exit(1);
}

if (str_contains($code, 'LITTYWATCH_PHASE5E_FIX1_SKIP_CANONICAL_PUNCTUATION')) {
    echo "Phase 5E FIX1 was al geïnstalleerd.\n";
    exit(0);
}

$needle = "    if(\$norm===\$canonicalNorm)continue;\n";
$replacement = $needle . "\n" .
"    // LITTYWATCH_PHASE5E_FIX1_SKIP_CANONICAL_PUNCTUATION\n" .
"    // Canonical spelling/punctuation corrections are not reusable market aliases.\n" .
"    \$punctuationInsensitiveAlias = preg_replace('/[^a-z0-9]+/iu','',\$norm) ?? \$norm;\n" .
"    \$punctuationInsensitiveCanonical = preg_replace('/[^a-z0-9]+/iu','',\$canonicalNorm) ?? \$canonicalNorm;\n" .
"    if(\$punctuationInsensitiveAlias === \$punctuationInsensitiveCanonical)continue;\n" .
"    if(\$norm === 'not in the face' && \$canonicalNorm === 'not the face')continue;\n";

$count = substr_count($code, $needle);
if ($count !== 1) {
    fwrite(STDERR, "ERROR: Anchor canonicalNorm verwacht 1x, gevonden {$count}x.\n");
    fwrite(STDERR, "Rollback niet nodig; bestand is niet gewijzigd.\n");
    exit(1);
}

$new = str_replace($needle, $replacement, $code);
if (file_put_contents($file, $new) === false) {
    copy($backupDir.'/dry-run.php', $file);
    fwrite(STDERR, "ERROR: schrijven mislukt. Rollback uitgevoerd.\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 5E FIX1 geïnstalleerd.\n";
echo "Backup: {$backupDir}\n";
echo "Draai nu:\n";
echo "  php tools/maintenance/phase5e/dry-run.php\n";
