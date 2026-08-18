<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$pdo = db();
$writer = $root . '/app/Market/StructuredOfferWriter.php';
$semantic = $root . '/app/Parser/SemanticNormalizer.php';

foreach ([$writer, $semantic] as $f) {
    if (!is_file($f)) {
        fwrite(STDERR, "ERROR: ontbreekt: {$f}\n");
        exit(1);
    }
}

$backup = $root . '/storage/backups/phase7e18-' . date('Ymd-His');
@mkdir($backup, 0775, true);
copy($writer, $backup . '/StructuredOfferWriter.php');
copy($semantic, $backup . '/SemanticNormalizer.php');

$src = __DIR__ . '/../../../app/Market/Phase7E18StructuralCleanupGuard.php';
$dst = $root . '/app/Market/Phase7E18StructuralCleanupGuard.php';
if (!is_file($src)) {
    fwrite(STDERR, "ERROR: Phase7E18StructuralCleanupGuard.php ontbreekt in pakket.\n");
    exit(1);
}
copy($src, $dst);

function n18(string $v): string {
    $v = mb_strtolower(trim(str_replace(['’','´','`'], "'", $v)));
    $v = preg_replace('/[^a-z0-9]+/u', ' ', $v) ?? $v;
    return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
}

function ensureAlias18(PDO $pdo, string $targetName, array $aliases): void {
    $st = $pdo->prepare("SELECT key FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");
    $st->execute([$targetName]);
    $key = $st->fetchColumn();
    if ($key === false) {
        echo "SKIP: target niet in KB: {$targetName}\n";
        return;
    }

    foreach ($aliases as $alias) {
        $norm = n18($alias);
        $st = $pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");
        $st->execute([$norm]);
        if ($st->fetchColumn() === false) {
            $pdo->prepare("INSERT INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES(?,?,?,?)")
                ->execute([(string)$key,$alias,$norm,'phase7e18']);
        }
    }
}

$pdo->beginTransaction();
try {
    ensureAlias18($pdo, 'Claws of the Broodmother', ['claws of bro']);
    ensureAlias18($pdo, 'Primeval Armor Remnant', ['primeval']);
    ensureAlias18($pdo, 'Deldrimor Armor Remnant', ['del armor rem','del armor rems']);
    ensureAlias18($pdo, 'Lockpick', ['lockpic','lockpics']);
    ensureAlias18($pdo, 'Honeycomb', ['honeycombs']);
    ensureAlias18($pdo, 'Birthday Cupcake', ['cumpcake','cumpcakes']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR: alias update mislukt: ".$e->getMessage()."\n");
    exit(1);
}

$code = file_get_contents($semantic);
if (!str_contains($code, 'LITTYWATCH_PHASE7E18_HONEYCOMB_CUPCAKE_SPLIT')) {
    $marker = 'LITTYWATCH_PHASE7E16_CONFIRMED_SHORTHAND';
    $p = strpos($code, $marker);
    if ($p === false) {
        $marker = 'LITTYWATCH_PHASE7E15_DOA_GEMS';
        $p = strpos($code, $marker);
    }
    if ($p === false) {
        fwrite(STDERR, "ERROR: SemanticNormalizer marker niet gevonden.\n");
        exit(1);
    }
    $lineEnd = strpos($code, "\n", $p);

    $block = <<<'PHP'

        // LITTYWATCH_PHASE7E18_HONEYCOMB_CUPCAKE_SPLIT
        $text = preg_replace(
            '/\bhoneycombs?\s*\/\s*cumpcakes?\b/iu',
            'Honeycomb | Birthday Cupcake',
            $text
        ) ?? $text;

        $text = preg_replace('/\bdel\s+armor\s+rems?\b/iu', 'Deldrimor Armor Remnant', $text) ?? $text;
        $text = preg_replace('/\bclaws\s+of\s+bro\b/iu', 'Claws of the Broodmother', $text) ?? $text;
        $text = preg_replace('/\b80\s*lockpics\b/iu', '80 Lockpick', $text) ?? $text;
PHP;

    $code = substr($code,0,$lineEnd+1) . $block . substr($code,$lineEnd+1);
    file_put_contents($semantic,$code);
}

$code = file_get_contents($writer);
if (!str_contains($code, 'LITTYWATCH_PHASE7E18_PREINSERT_STRUCTURAL_CLEANUP')) {
    $needle = "if(\$r['quality_status']==='accepted'){";
    $p = strpos($code, $needle);
    if ($p === false) {
        fwrite(STDERR, "ERROR: accepted branch niet gevonden.\n");
        exit(1);
    }

    $block =
        "     // LITTYWATCH_PHASE7E18_PREINSERT_STRUCTURAL_CLEANUP\n".
        "     \$r['_message']=(string)(\$message??'');\n".
        "     \$r=(new Phase7E18StructuralCleanupGuard(\$this->pdo))->repair(\$r);\n".
        "     unset(\$r['_message']);\n\n";

    $code = substr($code,0,$p) . $block . substr($code,$p);
    file_put_contents($writer,$code);
}

echo "OK: LittyWatch V5.2 Phase 7E.18 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Sharp Pointy Stick / Sunsp / Drago blijven bewust unresolved.\n";
echo "Echte miniatures zonder ded/unded blijven unresolved.\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E18StructuralCleanupGuard.php\n";
echo "  php -l app/Parser/SemanticNormalizer.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e18/smoke-test.php\n";
