<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$file = $root . '/app/Parser/ParserEngine.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: ParserEngine.php ontbreekt.\n");
    exit(1);
}

$code = file_get_contents($file);
if ($code === false) {
    fwrite(STDERR, "ERROR: ParserEngine.php kon niet worden gelezen.\n");
    exit(1);
}

$marker = 'LITTYWATCH_PHASE7E2_FIX3_BARE_MINIATURE_VARIANT_GATE';
if (str_contains($code, $marker)) {
    echo "Phase 7E.2 FIX3 staat al in ParserEngine.php.\n";
    exit(0);
}

$backup = $root . '/storage/backups/phase7e2-fix3-' . date('Ymd-His');
@mkdir($backup, 0775, true);
if (!copy($file, $backup . '/ParserEngine.php')) {
    fwrite(STDERR, "ERROR: backup mislukt.\n");
    exit(1);
}

$oldPipeline = <<<'PHP'
        $results = $this->restoreMiniatureDedication($results, $normalized);
        return $this->deduplicate($this->suppressGenericCatalogShadows($results, $normalized));
PHP;

$newPipeline = <<<'PHP'
        $results = $this->restoreMiniatureDedication($results, $normalized);
        // LITTYWATCH_PHASE7E2_FIX3_BARE_MINIATURE_VARIANT_GATE
        // Final invariant: a concrete miniature may only enter accepted market data
        // when dedication is known explicitly. This runs after shorthand/canonical
        // recovery so no earlier parser path can bypass the miniature variant policy.
        $results = $this->enforceMiniatureVariantGate($results);
        return $this->deduplicate($this->suppressGenericCatalogShadows($results, $normalized));
PHP;

if (!str_contains($code, $oldPipeline)) {
    @copy($backup . '/ParserEngine.php', $file);
    fwrite(STDERR, "ERROR: pipeline-anchor niet gevonden.\n");
    exit(1);
}
$code = str_replace($oldPipeline, $newPipeline, $code);

$anchor = <<<'PHP'
    /** @param list<ParsedOffer> $offers @return list<ParsedOffer> */
    private function restoreMiniatureDedication(array $offers, string $source): array
PHP;

$pos = strpos($code, $anchor);
if ($pos === false) {
    @copy($backup . '/ParserEngine.php', $file);
    fwrite(STDERR, "ERROR: restoreMiniatureDedication-anchor niet gevonden.\n");
    exit(1);
}

$method = <<<'PHP'
    /**
     * Phase 7E.2 FIX3
     *
     * A concrete miniature without explicit dedication is not a complete market
     * identity. Earlier parser paths may produce a valid catalog_match directly;
     * this final gate normalizes that outcome after dedication recovery.
     *
     * @param list<ParsedOffer> $offers
     * @return list<ParsedOffer>
     */
    private function enforceMiniatureVariantGate(array $offers): array
    {
        foreach ($offers as $index => $offer) {
            if (!str_starts_with(mb_strtolower(trim($offer->item)), 'miniature ')) {
                continue;
            }

            $dedication =
                $offer->modifiers['dedication']
                ?? $offer->relevantProperties['dedication']
                ?? null;

            if ($dedication === null && preg_match('/\|dedication:(dedicated|undedicated)(?:\||$)/iu', $offer->marketKey, $m)) {
                $dedication = mb_strtolower((string)$m[1]);
            }

            $hasDedication = in_array($dedication, ['dedicated', 'undedicated'], true);

            // Explicit dedication resolves exactly this one quarantine reason.
            if ($hasDedication && $offer->reason === 'miniature_variant_unresolved') {
                $offers[$index] = new ParsedOffer(
                    $offer->tradeType,
                    $offer->item,
                    $offer->itemKey,
                    $offer->modifiers,
                    $offer->price,
                    max(0.90, (float)$offer->confidence),
                    'accepted',
                    'catalog_match',
                    $offer->segment,
                    $offer->tokens,
                    $offer->profile,
                    $offer->relevantProperties,
                    $offer->marketKey,
                    $offer->exchange
                );
                continue;
            }

            if ($hasDedication) {
                continue;
            }

            // Never overwrite stronger/orthogonal rejection reasons.
            if (!in_array($offer->reason, ['catalog_match', 'low_confidence', 'miniature_variant_unresolved'], true)) {
                continue;
            }

            $offers[$index] = new ParsedOffer(
                $offer->tradeType,
                $offer->item,
                $offer->itemKey,
                $offer->modifiers,
                $offer->price,
                $offer->confidence,
                'review',
                'miniature_variant_unresolved',
                $offer->segment,
                $offer->tokens,
                $offer->profile,
                $offer->relevantProperties,
                preg_replace('/\|dedication:[^|]+/iu', '', $offer->marketKey) ?? $offer->marketKey,
                $offer->exchange
            );
        }

        return $offers;
    }

PHP;

$code = substr($code, 0, $pos) . $method . substr($code, $pos);

if (file_put_contents($file, $code) === false) {
    @copy($backup . '/ParserEngine.php', $file);
    fwrite(STDERR, "ERROR: schrijven mislukt; backup teruggezet.\n");
    exit(1);
}

exec('/usr/bin/php -l ' . escapeshellarg($file), $out, $rc);
if ($rc !== 0) {
    @copy($backup . '/ParserEngine.php', $file);
    fwrite(STDERR, "ERROR: syntaxfout; backup teruggezet.\n");
    fwrite(STDERR, implode("\n", $out) . "\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.2 FIX3 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Invariant:\n";
echo "  - concrete Miniature + explicit ded/unded => catalog_match toegestaan\n";
echo "  - concrete Miniature zonder dedication => miniature_variant_unresolved\n";
echo "  - overige rejection reasons worden niet overschreven\n";
echo "Draai nu:\n";
echo "  php -l app/Parser/ParserEngine.php\n";
echo "  php tools/maintenance/phase7e2-fix3/smoke-test.php\n";
