<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$files=[
    $root.'/app/Parser/Catalog.php',
    $root.'/app/Parser/ContextualSegmentExpander.php',
    $root.'/app/Parser/ParserEngine.php',
];

foreach($files as $file){
    if(!is_file($file)){fwrite(STDERR,"ERROR: ontbreekt: {$file}\n");exit(1);}
}

$backup=$root.'/storage/backups/phase7e3-fix1-'.date('Ymd-His');
@mkdir($backup,0775,true);
foreach($files as $file)copy($file,$backup.'/'.basename($file));

/* ------------------------------------------------------------------
 * 1. Catalog.php: aliases must live in the general matcher too.
 * ------------------------------------------------------------------ */
$file=$files[0];
$code=file_get_contents($file);

if(!str_contains($code,'LITTYWATCH_PHASE7E3_FIX1_CANONICAL_MINI_ALIASES')){
    $anchor="            'miniature forest griffon' => [";
    $pos=strpos($code,$anchor);
    if($pos===false){
        foreach($files as $restore){$b=$backup.'/'.basename($restore);if(is_file($b))copy($b,$restore);}
        fwrite(STDERR,"ERROR: Catalog miniature alias anchor niet gevonden.\n");exit(1);
    }

    $extra=<<<'PHP'
            // LITTYWATCH_PHASE7E3_FIX1_CANONICAL_MINI_ALIASES
            'miniature ghost of althea' => [
                'miniature ghost of althea', 'mini ghost of althea', 'ghost of althea', 'althea',
            ],
            'miniature varesh' => [
                'miniature varesh', 'mini varesh', 'varesh',
            ],
            'miniature dagnar stonepate' => [
                'miniature dagnar stonepate', 'mini dagnar stonepate', 'mini dagnar', 'dagnar stonepate', 'dagnar',
            ],
            'miniature black beast of aaaaarrrrrrggghhh' => [
                'miniature black beast of aaaaarrrrrrggghhh',
                'miniature black beast of aaaargh',
                'mini black beast',
                'black beast of aaaargh',
                'black beast',
            ],
            'miniature white rabbit' => [
                'miniature white rabbit', 'mini white rabbit',
            ],

PHP;
    $code=substr($code,0,$pos).$extra.substr($code,$pos);
    file_put_contents($file,$code);
}

/* ------------------------------------------------------------------
 * 2. ContextualSegmentExpander:
 *    "Unded Minis: Dagnar" => keep Dagnar and set active state.
 * ------------------------------------------------------------------ */
$file=$files[1];
$code=file_get_contents($file);

if(!str_contains($code,'LITTYWATCH_PHASE7E3_FIX1_HEADER_FIRST_MEMBER')){
    $anchor='            // LITTYWATCH_PHASE7E3_HEADER_PROPAGATION';
    if(!str_contains($code,$anchor)){
        $anchor='            $miniatureHeader = $this->miniatureHeader($segment);';
    }
    $pos=strpos($code,$anchor);
    if($pos===false){
        foreach($files as $restore){$b=$backup.'/'.basename($restore);if(is_file($b))copy($b,$restore);}
        fwrite(STDERR,"ERROR: ContextualSegmentExpander header anchor niet gevonden.\n");exit(1);
    }

    $inject=<<<'PHP'
            // LITTYWATCH_PHASE7E3_FIX1_HEADER_FIRST_MEMBER
            // Preserve the first member after an explicit miniature header:
            // "Unded Minis: Dagnar" must process "Dagnar", not consume it as header.
            if (preg_match('/^(unded(?:icated)?|ded(?:icated)?)\s+minis?(?:atures?)?\s*:\s*(.+)$/iu', trim($segment), $m7e3first)) {
                $activeMiniatureState = str_starts_with(mb_strtolower((string)$m7e3first[1]), 'unded') ? 'unded' : 'ded';
                $activeFamily = 'Miniature';
                $segment = trim((string)$m7e3first[2]);
            }

PHP;
    $code=substr($code,0,$pos).$inject.substr($code,$pos);
    file_put_contents($file,$code);
}

/* ------------------------------------------------------------------
 * 3. ParserEngine: propagate one explicit mini header state to concrete
 *    miniatures, then remove the fake generic header row.
 * ------------------------------------------------------------------ */
$file=$files[2];
$code=file_get_contents($file);

if(!str_contains($code,'LITTYWATCH_PHASE7E3_FIX1_MINI_HEADER_STATE_PROPAGATION')){
    $old='$results = $this->restoreMiniatureDedication($results, $normalized);';
    if(!str_contains($code,$old)){
        foreach($files as $restore){$b=$backup.'/'.basename($restore);if(is_file($b))copy($b,$restore);}
        fwrite(STDERR,"ERROR: ParserEngine dedication pipeline anchor niet gevonden.\n");exit(1);
    }

    $new=<<<'PHP'
        $results = $this->restoreMiniatureDedication($results, $normalized);
        // LITTYWATCH_PHASE7E3_FIX1_MINI_HEADER_STATE_PROPAGATION
        $results = $this->propagateMiniatureHeaderDedication($results, $normalized);
PHP;
    $code=str_replace($old,$new,$code);

    $anchor='    /** @param list<ParsedOffer> $offers @return list<ParsedOffer> */'."\n".'    private function restoreMiniatureDedication';
    $pos=strpos($code,$anchor);
    if($pos===false){
        foreach($files as $restore){$b=$backup.'/'.basename($restore);if(is_file($b))copy($b,$restore);}
        fwrite(STDERR,"ERROR: restoreMiniatureDedication method anchor niet gevonden.\n");exit(1);
    }

    $method=<<<'PHP'
    /**
     * Phase 7E.3 FIX1.
     *
     * A header such as "UNDED Minis:" explicitly owns the miniature list that
     * follows it. We propagate only when the complete source contains exactly
     * one unique miniature-header state. Explicit per-item dedication already
     * restored earlier always wins.
     *
     * @param list<ParsedOffer> $offers
     * @return list<ParsedOffer>
     */
    private function propagateMiniatureHeaderDedication(array $offers, string $source): array
    {
        $states=[];
        if(preg_match_all('/\b(unded(?:icated)?|ded(?:icated)?)\s+minis?(?:atures?)?\s*:/iu',$source,$m)){
            foreach($m[1] as $token){
                $states[]=str_starts_with(mb_strtolower((string)$token),'unded')
                    ? 'undedicated'
                    : 'dedicated';
            }
        }

        $states=array_values(array_unique($states));
        if(count($states)!==1)return $offers;

        $dedication=$states[0];
        $out=[];

        foreach($offers as $offer){
            $itemLower=mb_strtolower(trim($offer->item));
            $segmentLower=mb_strtolower(trim($offer->segment));

            // A generic Miniature row that only represents the list header is
            // context, not a tradable item. Do not persist it as an offer.
            if($itemLower==='miniature'
                && (
                    preg_match('/^(?:unded|undedicated|ded|dedicated|miniature|minis?)$/u',$segmentLower)
                    || str_contains($offer->marketKey,'dedication:')
                )){
                continue;
            }

            if(!str_starts_with($itemLower,'miniature ')){
                $out[]=$offer;
                continue;
            }

            $existing=$offer->modifiers['dedication']
                ?? $offer->relevantProperties['dedication']
                ?? null;

            if(in_array($existing,['dedicated','undedicated'],true)){
                $out[]=$offer;
                continue;
            }

            $modifiers=$offer->modifiers;
            $modifiers['dedication']=$dedication;
            $relevant=$offer->relevantProperties;
            $relevant['dedication']=$dedication;

            $marketKey=preg_replace('/\|dedication:[^|]+/iu','',$offer->marketKey)??$offer->marketKey;
            if($marketKey==='')$marketKey=$offer->itemKey;
            $marketKey.='|dedication:'.$dedication;

            $status=$offer->status;
            $reason=$offer->reason;
            $confidence=$offer->confidence;

            if($reason==='miniature_variant_unresolved'){
                $status='accepted';
                $reason='catalog_match';
                $confidence=max(0.90,(float)$confidence);
            }

            $out[]=new ParsedOffer(
                $offer->tradeType,
                $offer->item,
                $offer->itemKey,
                $modifiers,
                $offer->price,
                $confidence,
                $status,
                $reason,
                $offer->segment,
                $offer->tokens,
                $offer->profile,
                $relevant,
                $marketKey,
                $offer->exchange
            );
        }

        return $out;
    }

PHP;
    $code=substr($code,0,$pos).$method.substr($code,$pos);
    file_put_contents($file,$code);
}

/* Lint all; rollback all on any failure. */
foreach($files as $file){
    $out=[];$rc=0;
    exec('/usr/bin/php -l '.escapeshellarg($file),$out,$rc);
    if($rc!==0){
        foreach($files as $restore){$b=$backup.'/'.basename($restore);if(is_file($b))copy($b,$restore);}
        fwrite(STDERR,"ERROR: syntaxfout; volledige FIX1 teruggedraaid.\n");
        fwrite(STDERR,implode("\n",$out)."\n");
        exit(1);
    }
}

echo "OK: LittyWatch V5.2 Phase 7E.3 FIX1 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Fixes:\n";
echo "  - Catalog aliases voor Ghost of Althea, Varesh, Dagnar, Black Beast en Miniature White Rabbit\n";
echo "  - eerste member na 'Unded/Ded Minis:' blijft behouden\n";
echo "  - één expliciete list-header dedication wordt naar concrete miniatures doorgegeven\n";
echo "  - generieke Miniature header-row wordt niet als offer uitgegeven\n";
echo "Draai nu:\n";
echo "  php -l app/Parser/Catalog.php\n";
echo "  php -l app/Parser/ContextualSegmentExpander.php\n";
echo "  php -l app/Parser/ParserEngine.php\n";
echo "  php tools/maintenance/phase7e3-fix1/smoke-test.php\n";
