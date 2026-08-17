<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$file = $root . '/app/Market/StructuredOfferWriter.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php ontbreekt.\n");
    exit(1);
}

$backup = $root . '/storage/backups/phase7e8-fix3b-' . date('Ymd-His');
@mkdir($backup, 0775, true);
copy($file, $backup . '/StructuredOfferWriter.php');

$code = file_get_contents($file);
if ($code === false) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php kon niet worden gelezen.\n");
    exit(1);
}

if (str_contains($code, 'LITTYWATCH_PHASE7E8_FIX3B_PREINSERT_REVIEW_GUARD')) {
    echo "Phase 7E.8 FIX3B staat al geïnstalleerd.\n";
    echo "Backup: {$backup}\n";
    exit(0);
}

/*
 * Robust patch based on the actual writer currently on disk.
 * Do not depend on indentation/blank-line layout.
 */

$reconcile = '$r=$this->reconcileMiniatureVariant($r);';
$pos = strpos($code, $reconcile);
if ($pos === false) {
    fwrite(STDERR, "ERROR: reconcileMiniatureVariant-aanroep niet gevonden.\n");
    exit(1);
}

$insertPos = $pos + strlen($reconcile);

$guard = <<<'PHP'


     // LITTYWATCH_PHASE7E8_FIX3B_PREINSERT_REVIEW_GUARD
     // Review rows must pass named-item collision protection too.
     $r=(new Phase7E8NamedCollisionGuard($this->pdo))->repair($r);

     // LITTYWATCH_PHASE7E8_FIX3B_GENERIC_MINI_PREINSERT
     // Generic Miniature/Mini parser debris is never a valid market identity.
     $__lwBareMiniKey=str_replace('_','-',mb_strtolower(trim((string)($r['item_key']??''))));
     $__lwBareMiniName=mb_strtolower(trim((string)($r['item']??'')));
     if(in_array($__lwBareMiniKey,['miniature','mini'],true)
       || in_array($__lwBareMiniName,['miniature','mini'],true)){
       $r['quality_status']='rejected';
       $r['quality_reason']='strict_catalog_generic';
       $r['confidence']=min((float)($r['confidence']??0),0.40);
     }
PHP;

$code = substr($code, 0, $insertPos) . $guard . substr($code, $insertPos);

// Remove the old accepted-only named collision block by marker + next call.
$oldNamedMarker = '// LITTYWATCH_PHASE7E8_FIX1_NAMED_COLLISION';
$namedPos = strpos($code, $oldNamedMarker);
if ($namedPos !== false) {
    $call = '$r=(new Phase7E8NamedCollisionGuard($this->pdo))->repair($r);';
    $callPos = strpos($code, $call, $namedPos);
    if ($callPos === false) {
        fwrite(STDERR, "ERROR: oude named collision call niet gevonden na marker.\n");
        exit(1);
    }

    $end = $callPos + strlen($call);
    // Remove surrounding whitespace on that small block.
    while ($namedPos > 0 && ($code[$namedPos-1] === ' ' || $code[$namedPos-1] === "\t")) $namedPos--;
    while ($end < strlen($code) && ($code[$end] === "\r" || $code[$end] === "\n" || $code[$end] === ' ' || $code[$end] === "\t")) {
        if ($code[$end] === "\n") { $end++; break; }
        $end++;
    }
    $code = substr($code, 0, $namedPos) . substr($code, $end);
}

// Remove old accepted-only generic mini suppression block using its marker
// and the next "$variantGate=" statement as the end boundary.
$oldGenericMarker = '// LITTYWATCH_PHASE7E8_FIX1_GENERIC_MINI_SUPPRESS';
$genericPos = strpos($code, $oldGenericMarker);
if ($genericPos !== false) {
    $variantAnchor = '$variantGate=(new VariantValidityGate())->inspect($r);';
    $variantPos = strpos($code, $variantAnchor, $genericPos);
    if ($variantPos === false) {
        fwrite(STDERR, "ERROR: VariantValidityGate anker niet gevonden na generic mini marker.\n");
        exit(1);
    }

    // Preserve indentation immediately before variant gate.
    $code = substr($code, 0, $genericPos) . substr($code, $variantPos);
}

if (file_put_contents($file, $code) === false) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php schrijven mislukt.\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.8 FIX3B geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Draai nu:\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e8-fix3b/smoke-test.php\n";
