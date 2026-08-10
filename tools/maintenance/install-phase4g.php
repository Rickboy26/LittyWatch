<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$writerFile = $root . '/app/Market/StructuredOfferWriter.php';

if (!is_file($writerFile)) {
    fwrite(STDERR, "ERROR: StructuredOfferWriter.php ontbreekt.\n");
    exit(1);
}

$stamp = date('Ymd-His');
$backupDir = $root . '/storage/backups/phase4g-' . $stamp;
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden gemaakt.\n");
    exit(1);
}
copy($writerFile, $backupDir . '/StructuredOfferWriter.php');

function replace_once_4g(string $contents, string $needle, string $replacement, string $label): string
{
    $count = substr_count($contents, $needle);
    if ($count !== 1) {
        throw new RuntimeException("Anchor {$label} verwacht 1x, gevonden {$count}x.");
    }
    return str_replace($needle, $replacement, $contents);
}

try {
    $writer = (string)file_get_contents($writerFile);

    if (!str_contains($writer, 'LITTYWATCH_PHASE4G_RESIDUAL_CLASSIFIER')) {
        $old = <<<'PHP'
    // LITTYWATCH_PHASE4E_INSUFFICIENT_IDENTITY
    $mapped['quality_status']='review';
    $__lwItem=mb_strtolower(trim((string)($mapped['item']??'')));
    $__lwInsufficient=(bool)preg_match('/^(?:axe|axes|shield|shields|staff|staves|scythe|scythes|sword|swords|hammer|hammers|spear|spears|wand|wands|dagger|daggers|focus|focus item|bow|bows|flatbow|flatbows|hornbow|hornbows|longbow|longbows|recurve bow|recurvebow|shortbow|shortbows|elite tome|elite tomes|normal tome|normal tomes)$/u',$__lwItem);
    $mapped['quality_reason']=$__lwInsufficient?'insufficient_item_identity':'catalog_first_unresolved';
    $resolved=[$mapped];
PHP;

        $new = <<<'PHP'
    // LITTYWATCH_PHASE4E_INSUFFICIENT_IDENTITY
    // LITTYWATCH_PHASE4G_RESIDUAL_CLASSIFIER
    $mapped['quality_status']='review';

    $__lwItem=mb_strtolower(trim((string)($mapped['item']??'')));
    $__lwSegment=mb_strtolower(trim((string)($mapped['raw_segment']??$mapped['segment']??$mapped['item']??'')));
    $__lwText=trim($__lwItem.' '.$__lwSegment);

    $__lwInsufficient=(bool)preg_match(
        '/^(?:axe|axes|shield|shields|staff|staves|scythe|scythes|sword|swords|hammer|hammers|spear|spears|wand|wands|dagger|daggers|focus|focus item|bow|bows|flatbow|flatbows|hornbow|hornbows|longbow|longbows|recurve bow|recurvebow|shortbow|shortbows|elite tome|elite tomes|normal tome|normal tomes)$/u',
        $__lwItem
    );

    $__lwCollection = !$__lwInsufficient && (
        preg_match('/\b(?:q|req)\s*[0-9]+(?:\s*\/\s*(?:q|req)?\s*[0-9]+)?\s+(?:bows?|weapons?|items?|shields?|staves?|staffs?)\b/iu',$__lwText)
        || preg_match('/\b(?:gold|white|purple|green)\s+(?:items?|weapons?|minis?|miniatures?)\b/iu',$__lwText)
        || preg_match('/\b(?:os|old\s*school|pre[- ]?nerf|prenerf)\b.*\b(?:items?|mods?|weapons?|gold)\b/iu',$__lwText)
        || preg_match('/\b(?:all|any|many|package|collection)\s+(?:tomes?|minis?|miniatures?|tonics?|weapons?|items?)\b/iu',$__lwText)
        || preg_match('/\b(?:white\s+minis?|el\s+tonics?|minipet\s+package|gold\s+value\s+q[0-9]+)\b/iu',$__lwText)
        || preg_match('/\b(?:large\s+or\s+medium)\s+(?:eqbag|equipment\s+pack)\b/iu',$__lwText)
    );

    $__lwServiceNoise = !$__lwInsufficient && !$__lwCollection && (
        preg_match('/\b(?:running|run)\s+[a-z0-9 .\'-]+\s*(?:->|to)\s*[a-z0-9 .\'-]+/iu',$__lwText)
        || preg_match('/\b(?:trade\s+me|wsp\s+me|whisper\s+me|pm\s+me)\s*@?\s*(?:chest|here)?\b/iu',$__lwText)
        || preg_match('/\b(?:snowman\s+summoners?|runner|running\s+service|service|taxi|ferry)\b/iu',$__lwText)
        || preg_match('/^(?:for\s+1|little\s+john|demrikov)$/iu',$__lwItem)
    );

    $__lwModifierFragment = !$__lwInsufficient && !$__lwCollection && !$__lwServiceNoise && (
        preg_match('/^(?:\+?\s*30\s*hp|45\s*hp\s+w\s+ench|each\s*:\s*\+?\s*10\s+armor\s+vs|armor\s+\+?\s*[0-9]+\s+vs|40\/40\s+[a-z ]+\s+set)$/iu',$__lwItem)
        || (
            preg_match('/\b(?:staffhead|bowgrip|inscription)\b/iu',$__lwText)
            && !preg_match('/\b(?:of\s+the|of\s+fortitude|of\s+enchanting|of\s+shelter|of\s+warding)\b/iu',$__lwText)
        )
    );

    if ($__lwInsufficient) {
        $mapped['quality_reason']='insufficient_item_identity';
    } elseif ($__lwCollection) {
        $mapped['quality_reason']='collection_or_market_request';
    } elseif ($__lwServiceNoise) {
        $mapped['quality_reason']='service_or_noise';
    } elseif ($__lwModifierFragment) {
        $mapped['quality_reason']='modifier_fragment_unresolved';
    } else {
        $mapped['quality_reason']='catalog_first_unresolved';
    }

    $resolved=[$mapped];
PHP;

        $writer = replace_once_4g($writer, $old, $new, 'Phase 4E unresolved mapping');
        file_put_contents($writerFile, $writer);
    }

    $out=[]; $code=0;
    exec('php -l '.escapeshellarg($writerFile).' 2>&1', $out, $code);
    if ($code !== 0) {
        throw new RuntimeException("PHP syntaxcheck faalde:\n".implode("\n",$out));
    }

    echo "OK: LittyWatch V5.2 Phase 4G geïnstalleerd.\n";
    echo "Backup: {$backupDir}\n";
    echo "Nieuwe review-bakken:\n";
    echo "  collection_or_market_request\n";
    echo "  service_or_noise\n";
    echo "  modifier_fragment_unresolved\n";
    echo "\nDraai nu de volledige reparse opnieuw.\n";
    echo "Daarna: php tools/maintenance/report-phase4g.php\n";

} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: ".$e->getMessage()."\n");
    fwrite(STDERR, "Rollback vanuit {$backupDir}...\n");
    $backup=$backupDir.'/StructuredOfferWriter.php';
    if (is_file($backup)) @copy($backup,$writerFile);
    exit(1);
}
