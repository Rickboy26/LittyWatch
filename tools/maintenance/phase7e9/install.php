<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);

$semantic = $root . '/app/Parser/SemanticNormalizer.php';
$writer = $root . '/app/Market/StructuredOfferWriter.php';

foreach ([$semantic,$writer] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "ERROR: ontbreekt: {$file}\n");
        exit(1);
    }
}

$backup = $root . '/storage/backups/phase7e9-' . date('Ymd-His');
@mkdir($backup,0775,true);
copy($semantic,$backup.'/SemanticNormalizer.php');
copy($writer,$backup.'/StructuredOfferWriter.php');

$src = __DIR__ . '/../../../app/Market/Phase7E9LiveCleanupGuard.php';
$dst = $root . '/app/Market/Phase7E9LiveCleanupGuard.php';
if (!is_file($src)) {
    fwrite(STDERR, "ERROR: Phase7E9LiveCleanupGuard.php ontbreekt in pakket.\n");
    exit(1);
}
copy($src,$dst);

$code = file_get_contents($semantic);

if (!str_contains($code,'LITTYWATCH_PHASE7E9_EL_GHOSTLY_PRIEST')) {
    $anchor = "        \$text = trim(preg_replace('/\\\\s+/u',' ', \$text) ?? \$text);";
    $addition = $anchor . <<<'PHP'

        // LITTYWATCH_PHASE7E9_EL_GHOSTLY_PRIEST
        $text = preg_replace(
            '/\bEL\s+Ghostly\s+Priest\b/iu',
            'Everlasting Ghostly Priest Tonic',
            $text
        ) ?? $text;
PHP;
    if (!str_contains($code,$anchor)) {
        fwrite(STDERR,"ERROR: SemanticNormalizer begin-anker niet gevonden.\n");
        exit(1);
    }
    $code = str_replace($anchor,$addition,$code,$n);
    if ($n !== 1) {
        fwrite(STDERR,"ERROR: SemanticNormalizer begin-anker {$n}x vervangen.\n");
        exit(1);
    }
}

if (!str_contains($code,'LITTYWATCH_PHASE7E9_REGULAR_TOME_LIST')) {
    $needle = "        // LITTYWATCH_PHASE7E9_EL_GHOSTLY_PRIEST";
    $p = strpos($code,$needle);
    if ($p === false) {
        fwrite(STDERR,"ERROR: EL marker niet gevonden.\n");
        exit(1);
    }
    $end = strpos($code,") ?? \$text;",$p);
    if ($end === false) {
        fwrite(STDERR,"ERROR: EL statement einde niet gevonden.\n");
        exit(1);
    }
    $insertPos = $end + strlen(") ?? \$text;");
    $block = <<<'PHP'


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
    $code = substr($code,0,$insertPos).$block.substr($code,$insertPos);
}

if (file_put_contents($semantic,$code) === false) {
    fwrite(STDERR,"ERROR: SemanticNormalizer schrijven mislukt.\n");
    exit(1);
}

$code = file_get_contents($writer);

if (!str_contains($code,'LITTYWATCH_PHASE7E9_PREINSERT_CLEANUP')) {
    $accepted = "if(\$r['quality_status']==='accepted'){";
    $pos = strpos($code,$accepted);
    if ($pos === false) {
        fwrite(STDERR,"ERROR: accepted branch niet gevonden in StructuredOfferWriter.\n");
        exit(1);
    }

    $block = <<<'PHP'
     // LITTYWATCH_PHASE7E9_PREINSERT_CLEANUP
     $r=(new Phase7E9LiveCleanupGuard($this->pdo))->repair($r);

PHP;
    $code = substr($code,0,$pos).$block.substr($code,$pos);
}

if (file_put_contents($writer,$code) === false) {
    fwrite(STDERR,"ERROR: StructuredOfferWriter schrijven mislukt.\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.9 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Fixes:\n";
echo "  - EL Ghostly Priest => Everlasting Ghostly Priest Tonic context\n";
echo "  - D,Mo,N,Rt,W regular tomes => concrete profession tomes\n";
echo "  - unresolved miniatures krijgen canonical KB item-key\n";
echo "  - sweet / mod-and-scripts / generic weapon rows worden veilig rejected\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E9LiveCleanupGuard.php\n";
echo "  php -l app/Parser/SemanticNormalizer.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e9/smoke-test.php\n";
