<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$file = $root . '/app/Market/VariantNormalizer.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: VariantNormalizer.php ontbreekt.\n");
    exit(1);
}

$backupDir = $root . '/storage/backups/phase7e7-fix3-' . date('Ymd-His');
@mkdir($backupDir, 0775, true);
copy($file, $backupDir . '/VariantNormalizer.php');

$code = file_get_contents($file);
if ($code === false) {
    fwrite(STDERR, "ERROR: VariantNormalizer.php kon niet worden gelezen.\n");
    exit(1);
}

if (str_contains($code, 'LITTYWATCH_PHASE7E7_FIX3_CANONICAL_ITEM_KEY')) {
    echo "Phase 7E.7 FIX3 staat al in VariantNormalizer.php.\n";
    echo "Backup: {$backupDir}\n";
    exit(0);
}

$needle = '        $itemKey = $this->key($itemKey);';
$replacement = <<<'PHP'
        // LITTYWATCH_PHASE7E7_FIX3_CANONICAL_ITEM_KEY
        // Base item keys are catalog identities and therefore use hyphens.
        // Variant/property tokens below keep their existing underscore normalization.
        $itemKey = $this->itemKey($itemKey);
PHP;

if (!str_contains($code, $needle)) {
    fwrite(STDERR, "ERROR: normalize()-anker niet gevonden.\n");
    exit(1);
}
$code = str_replace($needle, $replacement, $code, $count);
if ($count !== 1) {
    fwrite(STDERR, "ERROR: normalize()-anker {$count}x gevonden.\n");
    exit(1);
}

$methodNeedle = <<<'PHP'
    private function key(string $value): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value)), '_');
    }
PHP;

$methodReplacement = <<<'PHP'
    private function itemKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace('_', '-', $value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        return trim($value, '-');
    }

    private function key(string $value): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value)), '_');
    }
PHP;

if (!str_contains($code, $methodNeedle)) {
    fwrite(STDERR, "ERROR: key()-anker niet gevonden.\n");
    exit(1);
}
$code = str_replace($methodNeedle, $methodReplacement, $code, $count2);
if ($count2 !== 1) {
    fwrite(STDERR, "ERROR: key()-anker {$count2}x vervangen.\n");
    exit(1);
}

if (file_put_contents($file, $code) === false) {
    fwrite(STDERR, "ERROR: schrijven mislukt.\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.7 FIX3 geïnstalleerd.\n";
echo "Canonical base item keys: hyphen-format.\n";
echo "Variant tokens: ongewijzigd underscore-format.\n";
echo "Backup: {$backupDir}\n";
echo "Draai nu:\n";
echo "  php -l app/Market/VariantNormalizer.php\n";
echo "  php tools/maintenance/phase7e7/smoke-test-fix3.php\n";
