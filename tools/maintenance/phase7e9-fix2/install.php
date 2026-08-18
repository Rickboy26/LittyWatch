<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$file = $root . '/app/Parser/SemanticNormalizer.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: SemanticNormalizer.php ontbreekt.\n");
    exit(1);
}

$backup = $root . '/storage/backups/phase7e9-fix2-' . date('Ymd-His');
@mkdir($backup, 0775, true);
copy($file, $backup . '/SemanticNormalizer.php');

$code = file_get_contents($file);
if ($code === false) {
    fwrite(STDERR, "ERROR: SemanticNormalizer.php kon niet worden gelezen.\n");
    exit(1);
}

if (str_contains($code, 'LITTYWATCH_PHASE7E9_FIX2_GHOSTLY_PRIEST_TONIC_GUARD')) {
    echo "Phase 7E.9 FIX2 staat al geïnstalleerd.\n";
    echo "Backup: {$backup}\n";
    exit(0);
}

$old = <<<'PHP'
        $text = preg_replace('/\bghostly\s+priest\b/iu', 'Miniature Ghostly Priest', $text) ?? $text;
PHP;

$new = <<<'PHP'
        // LITTYWATCH_PHASE7E9_FIX2_GHOSTLY_PRIEST_TONIC_GUARD
        // Bare "Ghostly Priest" is miniature shorthand, except when it is
        // already inside explicit Everlasting/Tonic context.
        $text = preg_replace(
            '/(?<!Everlasting )\bghostly\s+priest\b(?!\s+tonic\b)/iu',
            'Miniature Ghostly Priest',
            $text
        ) ?? $text;
PHP;

if (!str_contains($code, $old)) {
    fwrite(STDERR, "ERROR: Ghostly Priest generic rewrite niet gevonden; patch afgebroken.\n");
    exit(1);
}

$code = str_replace($old, $new, $code, $count);
if ($count !== 1) {
    fwrite(STDERR, "ERROR: Ghostly Priest rewrite {$count}x vervangen.\n");
    exit(1);
}

if (file_put_contents($file, $code) === false) {
    fwrite(STDERR, "ERROR: SemanticNormalizer.php schrijven mislukt.\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.9 FIX2 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Fix:\n";
echo "  - Everlasting Ghostly Priest Tonic blijft tonic\n";
echo "  - bare Ghostly Priest blijft miniature shorthand\n";
echo "  - ded/unded Ghostly Priest-regel blijft ongewijzigd\n";
echo "Draai nu:\n";
echo "  php -l app/Parser/SemanticNormalizer.php\n";
echo "  php tools/maintenance/phase7e9-fix2/smoke-test.php\n";
