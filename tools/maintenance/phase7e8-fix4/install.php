<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$file = $root . '/app/Market/Phase7E8NamedCollisionGuard.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: Phase7E8NamedCollisionGuard.php ontbreekt.\n");
    exit(1);
}

$backup = $root . '/storage/backups/phase7e8-fix4-' . date('Ymd-His');
@mkdir($backup, 0775, true);
copy($file, $backup . '/Phase7E8NamedCollisionGuard.php');

$code = file_get_contents($file);
if ($code === false) {
    fwrite(STDERR, "ERROR: guard-bestand kon niet worden gelezen.\n");
    exit(1);
}

if (str_contains($code, 'LITTYWATCH_PHASE7E8_FIX4_POSSESSIVE_FORTUNE')) {
    echo "Phase 7E.8 FIX4 staat al geïnstalleerd.\n";
    echo "Backup: {$backup}\n";
    exit(0);
}

$old = <<<'PHP'
        // LITTYWATCH_PHASE7E8_FIX2_FORTUNE_CASE
        // Kamadan text is often lowercase. Match Fortune names case-insensitively.
        // Preserve a canonical display form without ever promoting it to a miniature.
        if (preg_match("/\b([A-Za-z][A-Za-z'-]{2,})(?:'s)?\s+fortune\b/iu", $segment, $m)) {
            $name = mb_convert_case((string)$m[1], MB_CASE_TITLE, 'UTF-8');
            return $name . "'s Fortune";
        }
PHP;

$new = <<<'PHP'
        // LITTYWATCH_PHASE7E8_FIX4_POSSESSIVE_FORTUNE
        // Capture the proper name without swallowing an existing possessive "'s".
        // This prevents: kazhad's fortune -> Kazhad's's Fortune.
        if (preg_match("/\b([A-Za-z][A-Za-z'-]{2,}?)(?:'s)?\s+fortune\b/iu", $segment, $m)) {
            $name = trim((string)$m[1], " \t\n\r\0\x0B'");
            $name = preg_replace("/'s$/iu", '', $name) ?? $name;
            $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
            return $name . "'s Fortune";
        }
PHP;

if (!str_contains($code, $old)) {
    fwrite(STDERR, "ERROR: FIX2 Fortune-blok niet gevonden; patch afgebroken.\n");
    exit(1);
}

$code = str_replace($old, $new, $code, $count);
if ($count !== 1) {
    fwrite(STDERR, "ERROR: Fortune-blok {$count}x vervangen.\n");
    exit(1);
}

if (file_put_contents($file, $code) === false) {
    fwrite(STDERR, "ERROR: guard-bestand schrijven mislukt.\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.8 FIX4 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Fix:\n";
echo "  - kazhad's fortune => Kazhad's Fortune\n";
echo "  - geen dubbele possessive meer\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E8NamedCollisionGuard.php\n";
echo "  php tools/maintenance/phase7e8-fix4/smoke-test.php\n";
