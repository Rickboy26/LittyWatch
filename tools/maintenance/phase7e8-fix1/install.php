<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);

$semantic = $root . '/app/Parser/SemanticNormalizer.php';
$writer   = $root . '/app/Market/StructuredOfferWriter.php';

foreach ([$semantic,$writer] as $f) {
    if (!is_file($f)) {
        fwrite(STDERR, "ERROR: ontbreekt: {$f}\n");
        exit(1);
    }
}

$backup = $root . '/storage/backups/phase7e8-fix1-' . date('Ymd-His');
@mkdir($backup,0775,true);
copy($semantic,$backup.'/SemanticNormalizer.php');
copy($writer,$backup.'/StructuredOfferWriter.php');

$src = __DIR__ . '/../../../app/Market/Phase7E8NamedCollisionGuard.php';
$dst = $root . '/app/Market/Phase7E8NamedCollisionGuard.php';
if (!is_file($src)) {
    fwrite(STDERR, "ERROR: Phase7E8NamedCollisionGuard.php ontbreekt in pakket.\n");
    exit(1);
}
copy($src,$dst);

// 1) Narrow the overly broad Madruk shorthand.
// Old:
//   /\bmadr(?:uk)?\b(?!\s+dhuum)/ -> Miniature Madruk Dhuum
// This also catches "Madruk's Prophecy".
$code = file_get_contents($semantic);
if (!str_contains($code,'LITTYWATCH_PHASE7E8_FIX1_MADRUK_GUARD')) {
    $old = <<<'PHP'
        $text = preg_replace('/\bmadr(?:uk)?\b(?!\s+dhuum)/iu', 'Miniature Madruk Dhuum', $text) ?? $text;
PHP;
    $new = <<<'PHP'
        // LITTYWATCH_PHASE7E8_FIX1_MADRUK_GUARD
        // Never rewrite Madruk's Prophecy into a miniature. Only accept the
        // old "madr/madruk" shorthand when it is explicitly used as a mini.
        $text = preg_replace(
            "/\bmadr(?:uk)?\b(?=\s+(?:mini(?:ature|pet)?s?)\b)/iu",
            'Miniature Madruk Dhuum',
            $text
        ) ?? $text;
PHP;
    if (!str_contains($code,$old)) {
        fwrite(STDERR,"ERROR: Madruk SemanticNormalizer anker niet gevonden.\n");
        exit(1);
    }
    $code=str_replace($old,$new,$code,$n);
    if($n!==1){
        fwrite(STDERR,"ERROR: Madruk anker {$n}x vervangen.\n");
        exit(1);
    }
    file_put_contents($semantic,$code);
}

// 2) Apply exact named-item collision repair before StrictCatalogGate.
$code = file_get_contents($writer);
if (!str_contains($code,'LITTYWATCH_PHASE7E8_FIX1_NAMED_COLLISION')) {
    $anchor = <<<'PHP'
    // LITTYWATCH_PHASE7E8_LOCAL_CLAUSE_REPAIR
    $r=(new Phase7E8ClauseRepair())->repair($r);
PHP;
    $replacement = $anchor . <<<'PHP'

    // LITTYWATCH_PHASE7E8_FIX1_NAMED_COLLISION
    $r=(new Phase7E8NamedCollisionGuard($this->pdo))->repair($r);
PHP;
    if (!str_contains($code,$anchor)) {
        fwrite(STDERR,"ERROR: Phase7E8 writer anker niet gevonden. Is 7E.8 geïnstalleerd?\n");
        exit(1);
    }
    $code=str_replace($anchor,$replacement,$code,$n);
    if($n!==1){
        fwrite(STDERR,"ERROR: writer anker {$n}x vervangen.\n");
        exit(1);
    }
    file_put_contents($writer,$code);
}

// 3) Generic Miniature row suppression before insert.
// These rows are parser debris such as raw_segment="Miniature".
$code = file_get_contents($writer);
if (!str_contains($code,'LITTYWATCH_PHASE7E8_FIX1_GENERIC_MINI_SUPPRESS')) {
    $anchor = <<<'PHP'
     $r['item_key']=$normalized['item_key'];
     $r['normalized_market_key']=$normalized['market_key'];
PHP;
    $replacement = $anchor . <<<'PHP'

     // LITTYWATCH_PHASE7E8_FIX1_GENERIC_MINI_SUPPRESS
     $bareMiniKey=str_replace('_','-',mb_strtolower(trim((string)$r['item_key'])));
     $bareMiniName=mb_strtolower(trim((string)$r['item']));
     if(in_array($bareMiniKey,['miniature','mini'],true) || in_array($bareMiniName,['miniature','mini'],true)){
       $r['quality_status']='rejected';
       $r['quality_reason']='strict_catalog_generic';
       $r['confidence']=min((float)$r['confidence'],0.40);
     }
PHP;
    if (!str_contains($code,$anchor)) {
        fwrite(STDERR,"ERROR: normalized item_key writer anker niet gevonden.\n");
        exit(1);
    }
    $code=str_replace($anchor,$replacement,$code,$n);
    if($n!==1){
        fwrite(STDERR,"ERROR: generic mini anker {$n}x vervangen.\n");
        exit(1);
    }
    file_put_contents($writer,$code);
}

echo "OK: LittyWatch V5.2 Phase 7E.8 FIX1 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Fixes:\n";
echo "  - Madruk's Prophecy niet meer => Miniature Madruk Dhuum\n";
echo "  - Fortune/Prophecy collision guard vóór catalog gate\n";
echo "  - exact KB named item wordt canonical catalog_match\n";
echo "  - onbekende Fortune blijft unresolved, nooit false miniature\n";
echo "  - generieke Miniature rows worden strict_catalog_generic rejected\n";
echo "Draai nu:\n";
echo "  php -l app/Market/Phase7E8NamedCollisionGuard.php\n";
echo "  php -l app/Parser/SemanticNormalizer.php\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e8-fix1/smoke-test.php\n";
