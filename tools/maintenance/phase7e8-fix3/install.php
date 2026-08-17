<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$file = $root . '/app/Market/StructuredOfferWriter.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php ontbreekt.\n");
    exit(1);
}

$backup = $root . '/storage/backups/phase7e8-fix3-' . date('Ymd-His');
@mkdir($backup, 0775, true);
copy($file, $backup . '/StructuredOfferWriter.php');

$code = file_get_contents($file);
if ($code === false) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php kon niet worden gelezen.\n");
    exit(1);
}

if (str_contains($code, 'LITTYWATCH_PHASE7E8_FIX3_PREINSERT_REVIEW_GUARD')) {
    echo "Phase 7E.8 FIX3 staat al geïnstalleerd.\n";
    echo "Backup: {$backup}\n";
    exit(0);
}

/*
 * Root cause:
 * - NamedCollisionGuard and GenericMiniSuppress were located inside:
 *       if ($r['quality_status']==='accepted')
 * - miniature_variant_unresolved rows are already `review` after
 *   reconcileMiniatureVariant(), so they bypassed both guards completely.
 *
 * FIX3:
 * - Run NamedCollisionGuard for EVERY row immediately after miniature reconcile.
 * - Suppress generic Miniature/Mini rows for EVERY row before persistence.
 * - Keep BDS clause repair and StrictCatalogGate in the accepted branch.
 */

// 1. Insert unconditional pre-insert guard after reconcileMiniatureVariant().
$anchor = <<<'PHP'
     // LITTYWATCH_PHASE7E2_FIX4_WRITER_MINIATURE_VARIANT_INVARIANT
     // Final persistence invariant for concrete miniatures.
     $r=$this->reconcileMiniatureVariant($r);

     if($r['quality_status']==='accepted'){
PHP;

$replacement = <<<'PHP'
     // LITTYWATCH_PHASE7E2_FIX4_WRITER_MINIATURE_VARIANT_INVARIANT
     // Final persistence invariant for concrete miniatures.
     $r=$this->reconcileMiniatureVariant($r);

     // LITTYWATCH_PHASE7E8_FIX3_PREINSERT_REVIEW_GUARD
     // Review rows must pass the same named-item collision protection as
     // accepted rows. Otherwise false miniatures such as Kazhad's Fortune
     // bypass the guard entirely.
     $r=(new Phase7E8NamedCollisionGuard($this->pdo))->repair($r);

     // LITTYWATCH_PHASE7E8_FIX3_GENERIC_MINI_PREINSERT
     // Generic Miniature/Mini parser debris is never a real market identity.
     // Apply this invariant regardless of the incoming quality status.
     $__lwBareMiniKey=str_replace('_','-',mb_strtolower(trim((string)($r['item_key']??''))));
     $__lwBareMiniName=mb_strtolower(trim((string)($r['item']??'')));
     if(in_array($__lwBareMiniKey,['miniature','mini'],true)
       || in_array($__lwBareMiniName,['miniature','mini'],true)){
       $r['quality_status']='rejected';
       $r['quality_reason']='strict_catalog_generic';
       $r['confidence']=min((float)($r['confidence']??0),0.40);
     }

     if($r['quality_status']==='accepted'){
PHP;

if (!str_contains($code, $anchor)) {
    fwrite(STDERR, "ERROR: pre-insert anker niet gevonden; patch afgebroken.\n");
    exit(1);
}

$code = str_replace($anchor, $replacement, $code, $count);
if ($count !== 1) {
    fwrite(STDERR, "ERROR: pre-insert anker {$count}x vervangen.\n");
    exit(1);
}

// 2. Remove old accepted-only NamedCollisionGuard call to avoid duplicate work.
$oldNamed = <<<'PHP'
      // LITTYWATCH_PHASE7E8_FIX1_NAMED_COLLISION
    $r=(new Phase7E8NamedCollisionGuard($this->pdo))->repair($r);
PHP;

if (str_contains($code, $oldNamed)) {
    $code = str_replace($oldNamed, '', $code, $countNamed);
    if ($countNamed !== 1) {
        fwrite(STDERR, "ERROR: accepted-only named guard {$countNamed}x gevonden.\n");
        exit(1);
    }
}

// 3. Remove old accepted-only generic mini suppression block.
$oldGeneric = <<<'PHP'
       // LITTYWATCH_PHASE7E8_FIX1_GENERIC_MINI_SUPPRESS
       $bareMiniKey=str_replace('_','-',mb_strtolower(trim((string)$r['item_key'])));
       $bareMiniName=mb_strtolower(trim((string)$r['item']));
       if(in_array($bareMiniKey,['miniature','mini'],true) || in_array($bareMiniName,['miniature','mini'],true)){
         $r['quality_status']='rejected';
         $r['quality_reason']='strict_catalog_generic';
         $r['confidence']=min((float)$r['confidence'],0.40);
       }
PHP;

if (str_contains($code, $oldGeneric)) {
    $code = str_replace($oldGeneric, '', $code, $countGeneric);
    if ($countGeneric !== 1) {
        fwrite(STDERR, "ERROR: accepted-only generic mini block {$countGeneric}x gevonden.\n");
        exit(1);
    }
}

if (file_put_contents($file, $code) === false) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php schrijven mislukt.\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.8 FIX3 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Fix:\n";
echo "  - NamedCollisionGuard draait nu voor accepted én review rows\n";
echo "  - generieke Miniature/Mini rows worden vóór iedere insert rejected\n";
echo "  - BDS/StrictCatalogGate accepted-flow blijft intact\n";
echo "Draai nu:\n";
echo "  php -l app/Market/StructuredOfferWriter.php\n";
echo "  php tools/maintenance/phase7e8-fix3/smoke-test.php\n";
