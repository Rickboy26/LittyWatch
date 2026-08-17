<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$file = $root . '/app/Market/Phase7E8NamedCollisionGuard.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: Phase7E8NamedCollisionGuard.php ontbreekt.\n");
    exit(1);
}

$backup = $root . '/storage/backups/phase7e8-fix2-' . date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/Phase7E8NamedCollisionGuard.php');

$code = file_get_contents($file);
if ($code === false) {
    fwrite(STDERR, "ERROR: bestand kon niet gelezen worden.\n");
    exit(1);
}

if (str_contains($code,'LITTYWATCH_PHASE7E8_FIX2_FORTUNE_CASE')) {
    echo "Phase 7E.8 FIX2 staat al geïnstalleerd.\n";
    echo "Backup: {$backup}\n";
    exit(0);
}

$old = <<<'PHP'
        // Generic Fortune guard. Preserve the proper-name phrase, but never
        // invent a KB item if it does not exist.
        if (preg_match("/\b([A-Z][A-Za-z'-]{2,})(?:'s)?\s+Fortune\b/u", $segment, $m)) {
            return $m[1] . "'s Fortune";
        }
PHP;

$new = <<<'PHP'
        // LITTYWATCH_PHASE7E8_FIX2_FORTUNE_CASE
        // Kamadan text is often lowercase. Match Fortune names case-insensitively.
        // Preserve a canonical display form without ever promoting it to a miniature.
        if (preg_match("/\b([A-Za-z][A-Za-z'-]{2,})(?:'s)?\s+fortune\b/iu", $segment, $m)) {
            $name = mb_convert_case((string)$m[1], MB_CASE_TITLE, 'UTF-8');
            return $name . "'s Fortune";
        }
PHP;

if (!str_contains($code,$old)) {
    fwrite(STDERR,"ERROR: Fortune-anker niet gevonden; patch afgebroken.\n");
    exit(1);
}

$code = str_replace($old,$new,$code,$n);
if ($n !== 1) {
    fwrite(STDERR,"ERROR: Fortune-anker {$n}x vervangen.\n");
    exit(1);
}

file_put_contents($file,$code);

echo "OK: LittyWatch V5.2 Phase 7E.8 FIX2 geïnstalleerd.\n";
echo "Fix: lowercase Fortune clauses worden nu herkend.\n";
echo "Backup: {$backup}\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E8NamedCollisionGuard.php\n";
echo "  php tools/maintenance/phase7e8-fix2/smoke-test.php\n";
