<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$file = $root . '/app/Market/Phase7E21AcceptedSafetyGuard.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: Phase7E21AcceptedSafetyGuard.php ontbreekt.\n");
    exit(1);
}

$backup = $root . '/storage/backups/phase7e21-fix3-' . date('Ymd-His');
@mkdir($backup, 0775, true);
copy($file, $backup . '/Phase7E21AcceptedSafetyGuard.php');

$code = file_get_contents($file);
if ($code === false) {
    fwrite(STDERR, "ERROR: guard kon niet worden gelezen.\n");
    exit(1);
}

$old = "tac(?:tics)?|str(?:ength)?|command|comm|mot(?:ivation)?";
$new = "tac(?:t(?:ics)?)?|str(?:ength)?|command|comm|mot(?:ivation)?";

if (!str_contains($code, $old)) {
    if (str_contains($code, $new)) {
        echo "Phase 7E.21 FIX3 staat al in de guard.\n";
        exit(0);
    }

    fwrite(STDERR, "ERROR: verwacht shield-requirement patroon niet gevonden.\n");
    exit(1);
}

$code = str_replace($old, $new, $code);

if (file_put_contents($file, $code) === false) {
    fwrite(STDERR, "ERROR: guard kon niet worden bijgewerkt.\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.21 FIX3 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "FIX3 voegt 'Tact' toe als geldige shorthand voor Tactics.\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E21AcceptedSafetyGuard.php\n";
echo "  php tools/maintenance/phase7e21-fix3/smoke-test.php\n";
