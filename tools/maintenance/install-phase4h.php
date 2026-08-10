<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
$catalog=$root.'/app/Parser/Catalog.php';
$semantic=$root.'/app/Parser/SemanticNormalizer.php';
$writer=$root.'/app/Market/StructuredOfferWriter.php';
$data=$root.'/app/Data/phase4h-items.json';

foreach([$catalog,$semantic,$writer,$data] as $f){
    if(!is_file($f)){fwrite(STDERR,"ERROR: ontbreekt {$f}\n");exit(1);}
}

$stamp=date('Ymd-His');
$backup=$root.'/storage/backups/phase4h-'.$stamp;
if(!is_dir($backup)&&!mkdir($backup,0775,true)&&!is_dir($backup)){
    fwrite(STDERR,"ERROR: backupmap kon niet worden gemaakt\n");exit(1);
}
foreach([$catalog,$semantic,$writer] as $f) copy($f,$backup.'/'.basename($f));

function one(string $s,string $n,string $r,string $label):string{
    $c=substr_count($s,$n);
    if($c!==1)throw new RuntimeException("Anchor {$label} verwacht 1x, gevonden {$c}x.");
    return str_replace($n,$r,$s);
}
function regex_once(string $s,string $pattern,string $replacement,string $label):string{
    $count=0;
    $out=preg_replace($pattern,$replacement,$s,1,$count);
    if($out===null)throw new RuntimeException("Regex fout bij {$label}.");
    if($count!==1)throw new RuntimeException("Regex {$label} verwacht 1 match, gevonden {$count}.");
    return $out;
}

try{
    // 1) Small verified catalog supplement
    $c=(string)file_get_contents($catalog);
    if(!str_contains($c,'LITTYWATCH_PHASE4H_FINAL_CATALOG')){
        $a="        \$this->modifiers = \$this->loadJson(\$dataDir . '/modifiers.json');\n";
        $c=one($c,$a,
            "        // LITTYWATCH_PHASE4H_FINAL_CATALOG\n".
            "        \$phase4hItemsPath = \$dataDir . '/phase4h-items.json';\n".
            "        if (is_file(\$phase4hItemsPath)) {\n".
            "            \$this->items = \$this->mergeItems(\$this->items, \$this->loadJson(\$phase4hItemsPath));\n".
            "        }\n".$a,
            'catalog modifiers');
        file_put_contents($catalog,$c);
    }

    // 2) Narrow final normalizations
    $s=(string)file_get_contents($semantic);
    if(!str_contains($s,'LITTYWATCH_PHASE4H_FINAL_NORMALIZATION')){
        $a="        // LITTYWATCH_PHASE4F_RESIDUAL_NORMALIZATION_END\n";
        $r=<<<'PHP'
        // LITTYWATCH_PHASE4H_FINAL_NORMALIZATION
        $text=preg_replace('/\bArtifact\s+flame\b|\bFlame\s+Artifact(?:\s*\(\s*Fire\s+Magic\s*\))?\s+With\s+(?:the\s+)?Eye\b/iu','Flame Artifact',$text)??$text;
        $text=preg_replace('/\b(?:Stack\s+of\s+)?Obi(?:sidian)?\s+Shards?\b/iu','Obsidian Shard',$text)??$text;
        $text=preg_replace('/\bGranite(?:\s+Slabs?)?\b/iu','Granite Slab',$text)??$text;
        $text=preg_replace('/\bshiroken\s+assassin\s+mini(?:ature|pet)?\b/iu',"Miniature Shiro'ken Assassin",$text)??$text;
        // LITTYWATCH_PHASE4H_FINAL_NORMALIZATION_END

PHP;
        $s=one($s,$a,$r.$a,'semantic marker');
        file_put_contents($semantic,$s);
    }

    // 3) Phase 4G classifier corrections, tolerant of formatting differences
    $w=(string)file_get_contents($writer);
    if(!str_contains($w,'LITTYWATCH_PHASE4H_CLASSIFIER_FIX')){
        // Remove only the hard-coded service/noise names if present.
        $pattern='~\s*\|\|\s*preg_match\(\s*[\'"]\/\^\(\?:for\\\\s\+1\|little\\\\s\+john\|demrikov\)\$\/iu[\'"]\s*,\s*\$__lwItem\s*\)\s*~';
        $count=0;
        $tmp=preg_replace($pattern,"\n        // LITTYWATCH_PHASE4H_CLASSIFIER_FIX: removed hard-coded item names from service/noise.\n",$w,1,$count);
        if($tmp===null)throw new RuntimeException('Regex fout bij service classifier.');
        if($count>1)throw new RuntimeException("Service classifier matchte {$count}x.");
        $w=$tmp;

        // Add modifier attribute fragments at a stable semantic anchor.
        $anchor="    \$__lwModifierFragment = !\$__lwInsufficient && !\$__lwCollection && !\$__lwServiceNoise && (\n";
        if(substr_count($w,$anchor)!==1){
            throw new RuntimeException("Modifier-fragment anchor niet exact 1x gevonden.");
        }
        $rep=
            "    // LITTYWATCH_PHASE4H_CLASSIFIER_FIX\n".
            $anchor.
            "        preg_match('/\\b(?:[345]\\s*(?:sr|soul\\s*reaping|leadership|energy\\s*storage)\\s+for\\s+(?:bow|staff|scepter|wand)|sr\\s*\\+\\s*[345]\\s+for\\s+(?:bow|staff|scepter|wand))\\b/iu',\$__lwText)\n".
            "        || ";
        $w=str_replace($anchor,$rep,$w);

        file_put_contents($writer,$w);
    }

    json_decode((string)file_get_contents($data),true,flags:JSON_THROW_ON_ERROR);

    foreach([$catalog,$semantic,$writer] as $f){
        $o=[];$code=0;
        exec('php -l '.escapeshellarg($f).' 2>&1',$o,$code);
        if($code!==0)throw new RuntimeException("Syntaxcheck faalde {$f}:\n".implode("\n",$o));
    }

    require $root.'/bootstrap.php';
    $db=db();
    new \LittyWatch\Parser\Catalog($root.'/app/Data',$db);

    echo "OK: LittyWatch V5.2 Phase 4H FIX1 geïnstalleerd.\n";
    echo "Backup: {$backup}\n";
    echo "Draai nu:\n";
    echo "  php tools/maintenance/reparse-all.php\n";
    echo "  php tools/maintenance/report-phase4g.php\n";

}catch(Throwable $e){
    fwrite(STDERR,"ERROR: ".$e->getMessage()."\n");
    fwrite(STDERR,"Rollback vanuit {$backup}...\n");
    foreach([$catalog,$semantic,$writer] as $f){
        $b=$backup.'/'.basename($f);
        if(is_file($b))@copy($b,$f);
    }
    exit(1);
}
