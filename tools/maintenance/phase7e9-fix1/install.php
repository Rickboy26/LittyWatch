<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);

$semantic = $root . '/app/Parser/SemanticNormalizer.php';
$writer   = $root . '/app/Market/StructuredOfferWriter.php';
$guard    = $root . '/app/Market/Phase7E9LiveCleanupGuard.php';

foreach ([$semantic,$writer,$guard] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "ERROR: ontbreekt: {$file}\n");
        exit(1);
    }
}

$backup = $root . '/storage/backups/phase7e9-fix1-' . date('Ymd-His');
@mkdir($backup,0775,true);
copy($semantic,$backup.'/SemanticNormalizer.php');
copy($writer,$backup.'/StructuredOfferWriter.php');
copy($guard,$backup.'/Phase7E9LiveCleanupGuard.php');

/* -----------------------------------------------------------------------
 * 1) Fix Kuunavang canonicalization directly in guard.
 *    KB alias lookup can be ambiguous; canonical exact name is known.
 * --------------------------------------------------------------------- */
$code = file_get_contents($guard);
if (!str_contains($code,'LITTYWATCH_PHASE7E9_FIX1_KUUNAVANG_CANONICAL')) {
    $anchor = <<<'PHP'
        if (str_starts_with(mb_strtolower($item), 'miniature ')) {
PHP;
    $replacement = <<<'PHP'
        // LITTYWATCH_PHASE7E9_FIX1_KUUNAVANG_CANONICAL
        if (mb_strtolower($item) === 'miniature kuunavang') {
            $row['item'] = 'Miniature Kuunavang';
            $row['item_key'] = 'miniature-kuunavang';
            $row['market_key'] = 'miniature-kuunavang';
            if ($reason === 'miniature_variant_unresolved') {
                $row['quality_status'] = 'review';
                $row['quality_reason'] = 'miniature_variant_unresolved';
            }
        }

        if (str_starts_with(mb_strtolower($item), 'miniature ')) {
PHP;
    if (!str_contains($code,$anchor)) {
        fwrite(STDERR,"ERROR: guard miniature-anker niet gevonden.\n");
        exit(1);
    }
    $code = str_replace($anchor,$replacement,$code,$n);
    if ($n !== 1) {
        fwrite(STDERR,"ERROR: guard miniature-anker {$n}x vervangen.\n");
        exit(1);
    }
    file_put_contents($guard,$code);
}

/* -----------------------------------------------------------------------
 * 2) Robust SemanticNormalizer insert, without depending on exact whitespace.
 * --------------------------------------------------------------------- */
$code = file_get_contents($semantic);

if (!str_contains($code,'LITTYWATCH_PHASE7E9_EL_GHOSTLY_PRIEST')) {
    $classPos = strpos($code, 'function normalize');
    if ($classPos === false) $classPos = 0;

    $needle = '$text = trim(preg_replace(';
    $p = strpos($code,$needle,$classPos);
    if ($p === false) {
        fwrite(STDERR,"ERROR: eerste text trim in SemanticNormalizer niet gevonden.\n");
        exit(1);
    }

    $lineEnd = strpos($code,"\n",$p);
    if ($lineEnd === false) {
        fwrite(STDERR,"ERROR: regel-einde SemanticNormalizer niet gevonden.\n");
        exit(1);
    }

    $block = <<<'PHP'
        // LITTYWATCH_PHASE7E9_EL_GHOSTLY_PRIEST
        $text = preg_replace(
            '/\bEL\s+Ghostly\s+Priest\b/iu',
            'Everlasting Ghostly Priest Tonic',
            $text
        ) ?? $text;

        // LITTYWATCH_PHASE7E9_REGULAR_TOME_LIST
        $text = preg_replace_callback(
            '/\b((?:D|Mo|N|Rt|W)(?:\s*,\s*(?:D|Mo|N|Rt|W))+)\s+regular\s+tomes?\b/iu',
            static function(array $m): string {
                $map = [
                    'd' => 'Dervish Tome',
                    'mo' => 'Monk Tome',
                    'n' => 'Necromancer Tome',
                    'rt' => 'Ritualist Tome',
                    'w' => 'Warrior Tome',
                ];
                $out = [];
                foreach (preg_split('/\s*,\s*/u', (string)$m[1]) ?: [] as $token) {
                    $k = mb_strtolower(trim($token));
                    if (isset($map[$k])) $out[] = $map[$k];
                }
                return $out !== [] ? implode(' | ', $out) : (string)$m[0];
            },
            $text
        ) ?? $text;

PHP;
    $code = substr($code,0,$lineEnd+1).$block.substr($code,$lineEnd+1);
    file_put_contents($semantic,$code);
}

/* -----------------------------------------------------------------------
 * 3) Robust writer insert: place guard immediately before accepted branch.
 * --------------------------------------------------------------------- */
$code = file_get_contents($writer);

if (!str_contains($code,'LITTYWATCH_PHASE7E9_PREINSERT_CLEANUP')) {
    $accepted = "if(\$r['quality_status']==='accepted'){";
    $p = strpos($code,$accepted);
    if ($p === false) {
        fwrite(STDERR,"ERROR: accepted branch niet gevonden in StructuredOfferWriter.\n");
        exit(1);
    }

    $block = <<<'PHP'
     // LITTYWATCH_PHASE7E9_PREINSERT_CLEANUP
     $r=(new Phase7E9LiveCleanupGuard($this->pdo))->repair($r);

PHP;
    $code = substr($code,0,$p).$block.substr($code,$p);
    file_put_contents($writer,$code);
}

echo "OK: LittyWatch V5.2 Phase 7E.9 FIX1 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E9LiveCleanupGuard.php\n";
echo "  php -l app/Parser/SemanticNormalizer.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e9-fix1/smoke-test.php\n";
