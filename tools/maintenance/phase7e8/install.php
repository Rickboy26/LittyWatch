<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);

$files = [
    'phase7e' => $root . '/app/Market/Phase7ERecovery.php',
    'semantic' => $root . '/app/Parser/SemanticNormalizer.php',
    'writer' => $root . '/app/Market/StructuredOfferWriter.php',
];

foreach ($files as $name => $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "ERROR: {$file} ontbreekt.\n");
        exit(1);
    }
}

$backup = $root . '/storage/backups/phase7e8-' . date('Ymd-His');
@mkdir($backup, 0775, true);
foreach ($files as $file) {
    copy($file, $backup . '/' . basename($file));
}

$sourceRepair = __DIR__ . '/../../../app/Market/Phase7E8ClauseRepair.php';
$targetRepair = $root . '/app/Market/Phase7E8ClauseRepair.php';
if (!is_file($sourceRepair)) {
    fwrite(STDERR, "ERROR: Phase7E8ClauseRepair.php ontbreekt in pakket.\n");
    exit(1);
}
copy($sourceRepair, $targetRepair);

// -------------------------------------------------------------------------
// 1) Miniature recovery isolation:
//    only inspect this row's segment; do not append the full market message.
// -------------------------------------------------------------------------
$file = $files['phase7e'];
$code = file_get_contents($file);

if (!str_contains($code, 'LITTYWATCH_PHASE7E8_SEGMENT_ISOLATION')) {
    $needle = <<<'PHP'
        $segment=trim((string)($row['raw_segment']??''));
        $text=trim($segment.' '.$message);
        if($text==='')return null;
PHP;
    $replacement = <<<'PHP'
        $segment=trim((string)($row['raw_segment']??''));
        // LITTYWATCH_PHASE7E8_SEGMENT_ISOLATION
        // Never search the complete market message when resolving a single row:
        // that allowed a miniature name from a different clause to bleed into
        // this offer. Only fall back to the message when no segment exists.
        $text=$segment!==''?$segment:trim($message);
        if($text==='')return null;

        // Fortune/Prophecy are named items, not miniature offers.
        if(preg_match('/\b(?:fortune|prophecy)\b/iu',$segment))return null;

        // "EL <name>" is normally an Everlasting tonic shorthand. Do not turn
        // it into a miniature unless explicit miniature/dedication context exists.
        if(preg_match('/^\s*EL\b/iu',$segment)
            && !preg_match('/\bmini(?:ature|pet)?s?\b|\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu',$segment)){
            return null;
        }
PHP;
    if (!str_contains($code, $needle)) {
        fwrite(STDERR, "ERROR: Phase7ERecovery anker niet gevonden; patch afgebroken.\n");
        exit(1);
    }
    $code = str_replace($needle, $replacement, $code, $count);
    if ($count !== 1) {
        fwrite(STDERR, "ERROR: Phase7ERecovery anker {$count}x gevonden.\n");
        exit(1);
    }
    file_put_contents($file, $code);
}

// -------------------------------------------------------------------------
// 2) Live shorthand cleanup.
// -------------------------------------------------------------------------
$file = $files['semantic'];
$code = file_get_contents($file);

if (!str_contains($code, 'LITTYWATCH_PHASE7E8_LIVE_ALIASES')) {
    $anchor = <<<'PHP'
        $text=preg_replace('/\bshiroken\s+assassin\s+mini(?:ature|pet)?\b/iu',"Miniature Shiro'ken Assassin",$text)??$text;
PHP;
    $replacement = $anchor . <<<'PHP'

        // LITTYWATCH_PHASE7E8_LIVE_ALIASES
        $text=preg_replace('/\bpbeacons?\b/iu','Party Beacon',$text)??$text;
        $text=preg_replace('/\bgifts?\s+trav\b|\bgift\s+of\s+trav\b/iu','Gift of the Traveler',$text)??$text;
        $text=preg_replace('/\bdcakes?\b/iu','Birthday Cupcake',$text)??$text;
PHP;
    if (!str_contains($code, $anchor)) {
        fwrite(STDERR, "ERROR: SemanticNormalizer anker niet gevonden; patch afgebroken.\n");
        exit(1);
    }
    $code = str_replace($anchor, $replacement, $code, $count);
    if ($count !== 1) {
        fwrite(STDERR, "ERROR: SemanticNormalizer anker {$count}x gevonden.\n");
        exit(1);
    }
    file_put_contents($file, $code);
}

// -------------------------------------------------------------------------
// 3) Repair BDS local q/attribute before StrictCatalogGate / final variant
//    normalization. This is deliberately narrow and only touches BDS rows.
// -------------------------------------------------------------------------
$file = $files['writer'];
$code = file_get_contents($file);

if (!str_contains($code, 'LITTYWATCH_PHASE7E8_LOCAL_CLAUSE_REPAIR')) {
    $anchor = '$gate=(new StrictCatalogGate($this->pdo))->inspect((string)$r[\'item\'],(string)$r[\'item_key\']);';
    $replacement = <<<'PHP'
    // LITTYWATCH_PHASE7E8_LOCAL_CLAUSE_REPAIR
    $r=(new Phase7E8ClauseRepair())->repair($r);
    $gate=(new StrictCatalogGate($this->pdo))->inspect((string)$r['item'],(string)$r['item_key']);
PHP;
    if (!str_contains($code, $anchor)) {
        fwrite(STDERR, "ERROR: StructuredOfferWriter gate-anker niet gevonden; patch afgebroken.\n");
        exit(1);
    }
    $code = str_replace($anchor, $replacement, $code, $count);
    if ($count !== 1) {
        fwrite(STDERR, "ERROR: StructuredOfferWriter gate-anker {$count}x gevonden.\n");
        exit(1);
    }
    file_put_contents($file, $code);
}

echo "OK: LittyWatch V5.2 Phase 7E.8 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Wijzigingen:\n";
echo "  - miniature recovery geïsoleerd per segment\n";
echo "  - Fortune/Prophecy/EL-tonic false-mini guard\n";
echo "  - BDS lokale q/attribute repair\n";
echo "  - Pbeacons / gift trav / Dcakes live aliases\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E8ClauseRepair.php\n";
echo "  php -l app/Market/Phase7ERecovery.php\n";
echo "  php -l app/Parser/SemanticNormalizer.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e8/smoke-test.php\n";
