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
    fwrite(STDERR, "ERROR: ParserEngine.php kon niet gelezen worden.\n");
    exit(1);
}

$marker = 'LITTYWATCH_PHASE7E2_FIX2_MINIATURE_DEDICATION_PROMOTION';
if (str_contains($code, $marker)) {
    echo "Phase 7E.2 FIX2 staat al in ParserEngine.php.\n";
    exit(0);
}

$backup = $root . '/storage/backups/phase7e2-fix2-' . date('Ymd-His');
@mkdir($backup, 0775, true);
if (!copy($file, $backup . '/ParserEngine.php')) {
    fwrite(STDERR, "ERROR: backup mislukt.\n");
    exit(1);
}

$oldAliases = <<<'PHP'
            $name = preg_replace('/^Miniature\s+/iu', '', $offer->item) ?? $offer->item;
            $aliases = [preg_quote($name, '/')];
            if (mb_strtolower($name) === 'ghostly priest') $aliases[] = 'g\s*priest';
            $itemPattern = '(?:miniature\s+)?(?:' . implode('|', $aliases) . ')';
PHP;

$newAliases = <<<'PHP'
            // LITTYWATCH_PHASE7E2_FIX2_MINIATURE_DEDICATION_PROMOTION
            // Recovery understands the shorthand that resolved to the canonical
            // miniature, but dedication is still assigned only when ded/unded
            // is explicitly present in the original source text.
            $name = preg_replace('/^Miniature\s+/iu', '', $offer->item) ?? $offer->item;
            $aliases = [preg_quote($name, '/')];

            $miniatureSourceAliases = [
                'kuuna' => ['kuuna', 'kuun', 'kuunavang'],
                'rift_warden' => ['rift\s+warden', 'rift\s+war'],
                'miniature_dhuum' => ['dhuum', 'duum'],
                'ghostly_hero' => ['ghostly\s+hero', 'ghero'],
                'miniature_ghostly_hero' => ['ghostly\s+hero', 'ghero'],
                'miniature_flame_djinn' => ['flame\s+djinn?', 'flame\s+djin'],
                'miniature_water_djinn' => ['water\s+djinn?', 'water\s+djin'],
                'miniature_king_adelbern' => ['king\s+adelbern', 'adelbern'],
                'miniature_lich' => ['lich'],
                'miniature_shiro' => ['shiro'],
                'miniature_rift_warden' => ['rift\s+warden', 'rift\s+war'],
                'miniature_kuunavang' => ['kuuna', 'kuun', 'kuunavang'],
            ];

            $itemKeyLower = mb_strtolower((string)$offer->itemKey);
            foreach ($miniatureSourceAliases[$itemKeyLower] ?? [] as $aliasPattern) {
                $aliases[] = $aliasPattern;
            }

            if (mb_strtolower($name) === 'ghostly priest') $aliases[] = 'g\s*priest';
            $aliases = array_values(array_unique($aliases));
            $itemPattern = '(?:miniature\s+|mini\s+)?(?:' . implode('|', $aliases) . ')';
PHP;

if (!str_contains($code, $oldAliases)) {
    @copy($backup . '/ParserEngine.php', $file);
    fwrite(STDERR, "ERROR: restoreMiniatureDedication alias-anchor niet gevonden.\n");
    exit(1);
}
$code = str_replace($oldAliases, $newAliases, $code);

$oldConstruct = <<<'PHP'
            $offers[$index] = new ParsedOffer(
                $offer->tradeType,
                $offer->item,
                $offer->itemKey,
                $modifiers,
                $offer->price,
                $offer->confidence,
                $offer->status,
                $offer->reason,
PHP;

$newConstruct = <<<'PHP'
            // A later explicit dedication recovery resolves exactly one earlier
            // uncertainty: miniature_variant_unresolved. Promote only that reason.
            // Every other review/rejection reason remains untouched.
            $status = $offer->status;
            $reason = $offer->reason;
            $confidence = $offer->confidence;
            if ($reason === 'miniature_variant_unresolved') {
                $status = 'accepted';
                $reason = 'catalog_match';
                $confidence = max(0.90, (float)$confidence);
            }

            $offers[$index] = new ParsedOffer(
                $offer->tradeType,
                $offer->item,
                $offer->itemKey,
                $modifiers,
                $offer->price,
                $confidence,
                $status,
                $reason,
PHP;

if (!str_contains($code, $oldConstruct)) {
    @copy($backup . '/ParserEngine.php', $file);
    fwrite(STDERR, "ERROR: ParsedOffer construct-anchor niet gevonden.\n");
    exit(1);
}
$code = str_replace($oldConstruct, $newConstruct, $code);

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

echo "OK: LittyWatch V5.2 Phase 7E.2 FIX2 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Fixes:\n";
echo "  - restored dedication promoveert miniature_variant_unresolved -> catalog_match\n";
echo "  - Kuuna/Kuun/Kuunavang worden door dedication restore herkend\n";
echo "  - Rift War/Rift Warden en overige 7E.2 shorthand blijven contextueel herkenbaar\n";
echo "  - dedication wordt nog steeds ALLEEN uit expliciet ded/unded in bron gehaald\n";
echo "Draai nu:\n";
echo "  php -l app/Parser/ParserEngine.php\n";
echo "  php tools/maintenance/phase7e2-fix2/smoke-test.php\n";
