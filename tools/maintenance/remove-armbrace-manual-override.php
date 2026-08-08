<?php
declare(strict_types=1);

/**
 * Verwijdert alleen het door de installer toegevoegde Armbrace override-blok.
 *
 * Usage:
 *   php tools/maintenance/remove-armbrace-manual-override.php
 */

$root = dirname(__DIR__, 2);
$target = $root . '/item-image.php';

if (!is_file($target)) {
    fwrite(STDERR, "ERROR: item-image.php niet gevonden.\n");
    exit(1);
}

$contents = file_get_contents($target);
if ($contents === false) {
    fwrite(STDERR, "ERROR: item-image.php kon niet worden gelezen.\n");
    exit(1);
}

$pattern = '~<\?php\s*// LITTYWATCH_ARM_BRACE_MANUAL_OVERRIDE_BEGIN.*?// LITTYWATCH_ARM_BRACE_MANUAL_OVERRIDE_END\s*\?>\s*~s';

$new = preg_replace($pattern, '', $contents, 1, $count);
if ($new === null) {
    fwrite(STDERR, "ERROR: override kon niet worden verwijderd.\n");
    exit(1);
}

if ($count === 0) {
    echo "Geen Armbrace manual override gevonden.\n";
    exit(0);
}

if (file_put_contents($target, $new) === false) {
    fwrite(STDERR, "ERROR: item-image.php kon niet worden bijgewerkt.\n");
    exit(1);
}

echo "OK: Armbrace manual override verwijderd.\n";
