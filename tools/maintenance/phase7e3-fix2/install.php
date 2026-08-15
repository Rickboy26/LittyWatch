<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$files=[
    $root.'/app/Parser/Catalog.php',
    $root.'/app/Parser/ParserEngine.php',
    $root.'/app/Market/StructuredOfferWriter.php',
    $root.'/app/Parser/MarketBundleExpander.php',
];

foreach($files as $file){
    if(!is_file($file)){
        fwrite(STDERR,"ERROR: ontbreekt: {$file}\n");
        exit(1);
    }
}

$backup=$root.'/storage/backups/phase7e3-fix2-'.date('Ymd-His');
@mkdir($backup,0775,true);
foreach($files as $file)copy($file,$backup.'/'.basename($file));

/* -------------------------------------------------------------
 * 1. Catalog corrections: Prince Rurik != Undead Prince
 *    and Zhang => High Priest Zhang.
 * ------------------------------------------------------------- */
$file=$root.'/app/Parser/Catalog.php';
$code=file_get_contents($file);

if(!str_contains($code,'LITTYWATCH_PHASE7E3_FIX2_CANONICAL_CORRECTIONS')){
    // Add explicit aliases to correct existing canonicals.
    $anchor="            'miniature rift warden' => [";
    $pos=strpos($code,$anchor);
    if($pos===false){
        fwrite(STDERR,"ERROR: Catalog anchor niet gevonden.\n");
        exit(1);
    }

    $extra=<<<'PHP'
            // LITTYWATCH_PHASE7E3_FIX2_CANONICAL_CORRECTIONS
            'miniature prince rurik' => [
                'miniature prince rurik', 'mini prince rurik', 'prince rurik',
            ],
            'miniature undead prince' => [
                'miniature undead prince', 'mini undead prince', 'undead prince',
            ],
            'miniature high priest zhang' => [
                'miniature high priest zhang', 'mini high priest zhang',
                'high priest zhang', 'zhang',
            ],

PHP;
    $code=substr($code,0,$pos).$extra.substr($code,$pos);

    // Remove any dangerous accidental "prince rurik" alias from Undead Prince block.
    $code=preg_replace(
        "/('miniature undead prince(?: rurik)?'\\s*=>\\s*\\[[^\\]]*)(?:'prince rurik'\\s*,?)([^\\]]*\\])/su",
        "$1$2",
        $code
    ) ?? $code;

    file_put_contents($file,$code);
}

/* -------------------------------------------------------------
 * 2. MarketBundleExpander corrections.
 * ------------------------------------------------------------- */
$file=$root.'/app/Parser/MarketBundleExpander.php';
$code=file_get_contents($file);

if(!str_contains($code,'LITTYWATCH_PHASE7E3_FIX2_BUNDLE_CANONICAL_CORRECTIONS')){
    $code=str_replace(
        "'prince rurik'=>'Miniature Undead Prince',",
        "'prince rurik'=>'Miniature Prince Rurik', // LITTYWATCH_PHASE7E3_FIX2_BUNDLE_CANONICAL_CORRECTIONS",
        $code
    );
    $code=str_replace(
        "'zhang'=>'Miniature Zhang',",
        "'zhang'=>'Miniature High Priest Zhang', // LITTYWATCH_PHASE7E3_FIX2_BUNDLE_CANONICAL_CORRECTIONS",
        $code
    );
    file_put_contents($file,$code);
}

/* -------------------------------------------------------------
 * 3. ParserEngine: central miniature semantics helper.
 *    All Miniature* names + exactly seven official exceptions.
 * ------------------------------------------------------------- */
$file=$root.'/app/Parser/ParserEngine.php';
$code=file_get_contents($file);

if(!str_contains($code,'LITTYWATCH_PHASE7E3_FIX2_MINIATURE_REGISTRY')){
    $anchor='    /** @param list<ParsedOffer> $offers @return list<ParsedOffer> */'."\n".'    private function restoreMiniatureDedication';
    $pos=strpos($code,$anchor);
    if($pos===false){
        fwrite(STDERR,"ERROR: ParserEngine helper anchor niet gevonden.\n");
        exit(1);
    }

    $helper=<<<'PHP'
    // LITTYWATCH_PHASE7E3_FIX2_MINIATURE_REGISTRY
    private function isMiniatureOffer(ParsedOffer $offer): bool
    {
        $item=mb_strtolower(trim($offer->item));
        if(str_starts_with($item,'miniature '))return true;

        return in_array($item,[
            'white rabbit',
            'black moa chick',
            'brown rabbit',
            'gwen doll',
            'the frog',
            'the frog [halloween]',
            'the frog [wintersday]',
        ],true);
    }

PHP;
    $code=substr($code,0,$pos).$helper.substr($code,$pos);

    // Replace known Miniature-name guards with registry helper.
    $replacements=[
        "if (!str_starts_with(mb_strtolower(\$offer->item), 'miniature ')) continue;" =>
        "if (!\$this->isMiniatureOffer(\$offer)) continue;",
        "if (!str_starts_with(mb_strtolower(trim(\$offer->item)), 'miniature ')) {" =>
        "if (!\$this->isMiniatureOffer(\$offer)) {",
        "if(!str_starts_with(\$itemLower,'miniature ')){" =>
        "if(!\$this->isMiniatureOffer(\$offer)){",
    ];
    foreach($replacements as $old=>$new){
        if(str_contains($code,$old))$code=str_replace($old,$new,$code);
    }

    file_put_contents($file,$code);
}

/* -------------------------------------------------------------
 * 4. Writer final invariant uses same seven exceptions.
 * ------------------------------------------------------------- */
$file=$root.'/app/Market/StructuredOfferWriter.php';
$code=file_get_contents($file);

if(!str_contains($code,'LITTYWATCH_PHASE7E3_FIX2_WRITER_MINIATURE_REGISTRY')){
    $old=<<<'PHP'
    $item=mb_strtolower(trim((string)($row['item']??'')));
    if(!str_starts_with($item,'miniature '))return $row;
PHP;

    $new=<<<'PHP'
    // LITTYWATCH_PHASE7E3_FIX2_WRITER_MINIATURE_REGISTRY
    $item=mb_strtolower(trim((string)($row['item']??'')));
    $isMiniature=
        str_starts_with($item,'miniature ')
        || in_array($item,[
            'white rabbit',
            'black moa chick',
            'brown rabbit',
            'gwen doll',
            'the frog',
            'the frog [halloween]',
            'the frog [wintersday]',
        ],true);

    if(!$isMiniature)return $row;
PHP;

    if(!str_contains($code,$old)){
        fwrite(STDERR,"ERROR: StructuredOfferWriter miniature guard niet gevonden.\n");
        exit(1);
    }

    $code=str_replace($old,$new,$code);
    file_put_contents($file,$code);
}

/* Lint + rollback */
foreach($files as $file){
    $out=[];$rc=0;
    exec('/usr/bin/php -l '.escapeshellarg($file),$out,$rc);
    if($rc!==0){
        foreach($files as $restore){
            $b=$backup.'/'.basename($restore);
            if(is_file($b))copy($b,$restore);
        }
        fwrite(STDERR,"ERROR: syntaxfout; volledige FIX2 teruggedraaid.\n");
        fwrite(STDERR,implode("\n",$out)."\n");
        exit(1);
    }
}

echo "OK: LittyWatch V5.2 Phase 7E.3 FIX2 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Canonical corrections:\n";
echo "  - Prince Rurik => Miniature Prince Rurik\n";
echo "  - Undead Prince => Miniature Undead Prince\n";
echo "  - Zhang => Miniature High Priest Zhang\n";
echo "Miniature type exceptions:\n";
echo "  White Rabbit, Black Moa Chick, Brown Rabbit, Gwen Doll,\n";
echo "  The Frog, The Frog [Halloween], The Frog [Wintersday]\n";
