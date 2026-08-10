<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$catalogFile  = $root . '/app/Parser/Catalog.php';
$resolverFile = $root . '/app/Market/CatalogFirstResolver.php';
$gateFile     = $root . '/app/Market/StrictCatalogGate.php';
$writerFile   = $root . '/app/Market/StructuredOfferWriter.php';
$bundleFile   = $root . '/app/Parser/MarketBundleExpander.php';
$dataFile     = $root . '/app/Data/phase4e-items.json';

foreach ([$catalogFile,$resolverFile,$gateFile,$writerFile,$bundleFile,$dataFile] as $f) {
    if (!is_file($f)) { fwrite(STDERR,"ERROR: ontbreekt: {$f}\n"); exit(1); }
}

$stamp=date('Ymd-His');
$backupDir=$root.'/storage/backups/phase4e-'.$stamp;
if(!is_dir($backupDir) && !mkdir($backupDir,0775,true) && !is_dir($backupDir)){
    fwrite(STDERR,"ERROR: backupmap kon niet worden gemaakt\n"); exit(1);
}
foreach([$catalogFile,$resolverFile,$gateFile,$writerFile,$bundleFile] as $f){
    if(!copy($f,$backupDir.'/'.basename($f))){fwrite(STDERR,"ERROR: backup mislukt {$f}\n");exit(1);}
}

function rep1(string $s,string $n,string $r,string $label): string {
    $c=substr_count($s,$n);
    if($c!==1) throw new RuntimeException("Anchor {$label} verwacht 1x, gevonden {$c}x.");
    return str_replace($n,$r,$s);
}
function wr(string $f,string $s): void {
    if(file_put_contents($f,$s)===false) throw new RuntimeException("Kon {$f} niet schrijven.");
}

try {
    $catalog=(string)file_get_contents($catalogFile);
    if(!str_contains($catalog,'LITTYWATCH_PHASE4E_MARKET_IDENTITIES')){
        $a="        \$this->modifiers = \$this->loadJson(\$dataDir . '/modifiers.json');\n";
        $catalog=rep1($catalog,$a,
            "        // LITTYWATCH_PHASE4E_MARKET_IDENTITIES\n".
            "        \$phase4eItemsPath = \$dataDir . '/phase4e-items.json';\n".
            "        if (is_file(\$phase4eItemsPath)) {\n".
            "            \$this->items = \$this->mergeItems(\$this->items, \$this->loadJson(\$phase4eItemsPath));\n".
            "        }\n".$a,
            'Catalog modifiers');
        wr($catalogFile,$catalog);
    }

    $resolver=(string)file_get_contents($resolverFile);
    if(!str_contains($resolver,'LITTYWATCH_PHASE4E_NAME_FIRST_EXACT')){
        $old=<<<'PHP'
    private function catalogueExact(string $name,string $key): ?array
    {
        $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND (key=:k OR lower(trim(name))=lower(trim(:n))) ORDER BY CASE WHEN key=:k2 THEN 0 ELSE 1 END LIMIT 1");
        $st->execute([':k'=>$key,':k2'=>$key,':n'=>trim($name)]);$r=$st->fetch();
        return $r?['key'=>(string)$r['key'],'name'=>CanonicalMarketIdentity::nameFor((string)$r['name'],(string)$r['key'])]:null;
    }
PHP;
        $new=<<<'PHP'
    private function catalogueExact(string $name,string $key): ?array
    {
        // LITTYWATCH_PHASE4E_NAME_FIRST_EXACT
        $name=trim($name);$key=trim($key);
        if($name!==''){
            $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(:n)) LIMIT 1");
            $st->execute([':n'=>$name]);$r=$st->fetch();
            if($r)return ['key'=>(string)$r['key'],'name'=>CanonicalMarketIdentity::nameFor((string)$r['name'],(string)$r['key'])];
        }
        if($key!==''){
            $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND key=:k LIMIT 1");
            $st->execute([':k'=>$key]);$r=$st->fetch();
            if($r)return ['key'=>(string)$r['key'],'name'=>CanonicalMarketIdentity::nameFor((string)$r['name'],(string)$r['key'])];
        }
        return null;
    }
PHP;
        $resolver=rep1($resolver,$old,$new,'resolver exact');
    }

    if(!str_contains($resolver,'LITTYWATCH_PHASE4E_MINIATURE_QUARANTINE')){
        $old="        \$state=\$this->miniState(\$message.' '.\$item);\n        if(\$state===null)return []; // no ded/unded = review, never player market\n";
        $new=
            "        // LITTYWATCH_PHASE4E_MINIATURE_QUARANTINE\n".
            "        \$segment=trim((string)(\$row['raw_segment']??''));\n".
            "        if(preg_match('/\\b(?:potion|tonic)\\b/iu',\$segment) && !preg_match('/\\bmini(?:ature|pet)?s?\\b|\\b(?:unded(?:icated)?|ded(?:icated)?)\\b/iu',\$segment)){\n".
            "            \$row['quality_status']='review';\$row['quality_reason']='miniature_context_conflict';return [\$row];\n".
            "        }\n".
            "        \$state=\$this->miniState(\$message.' '.\$item);\n".
            "        if(\$state===null){\$row['quality_status']='review';\$row['quality_reason']='miniature_variant_unresolved';return [\$row];}\n";
        $resolver=rep1($resolver,$old,$new,'mini state');
    }
    wr($resolverFile,$resolver);

    $gate=(string)file_get_contents($gateFile);
    if(!str_contains($gate,'LITTYWATCH_PHASE4E_NAME_FIRST_GATE')){
        $old=<<<'PHP'
        // Exact active KB key/name is authoritative.
        $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND (key=:key OR lower(trim(name))=lower(trim(:name))) ORDER BY CASE WHEN key=:key2 THEN 0 ELSE 1 END LIMIT 1");
        $st->execute([':key'=>$key,':key2'=>$key,':name'=>$name]);
        $row=$st->fetch();
PHP;
        $new=<<<'PHP'
        // LITTYWATCH_PHASE4E_NAME_FIRST_GATE
        $row=false;
        if($name!==''){
            $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(:name)) LIMIT 1");
            $st->execute([':name'=>$name]);$row=$st->fetch();
        }
        if(!$row&&$key!==''){
            $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND key=:key LIMIT 1");
            $st->execute([':key'=>$key]);$row=$st->fetch();
        }
PHP;
        $gate=rep1($gate,$old,$new,'gate exact');
        wr($gateFile,$gate);
    }

    $bundle=(string)file_get_contents($bundleFile);
    if(!str_contains($bundle,'LITTYWATCH_PHASE4E_SHARED_MINI_STATE')){
        $a=<<<'PHP'
        // No explicit "mini" header: accept slash/comma lists only if every
        // member is a known miniature shorthand.
        $implicit = false;
PHP;
        $r=<<<'PHP'
        // LITTYWATCH_PHASE4E_SHARED_MINI_STATE
        if ($body === null && preg_match('/^(?:wts|wtb|wtt)?\s*(unded(?:icated)?|ded(?:icated)?)\s+(.+[,\/].+)$/iu',trim($text),$sm)) {
            $state = $this->state($sm[1]);
            $body = trim($sm[2]);
        }

        // No explicit "mini" header: accept slash/comma lists only if every
        // member is a known miniature shorthand.
        $implicit = false;
PHP;
        $bundle=rep1($bundle,$a,$r,'bundle mini state');
        wr($bundleFile,$bundle);
    }

    $writer=(string)file_get_contents($writerFile);
    if(!str_contains($writer,'LITTYWATCH_PHASE4E_INSUFFICIENT_IDENTITY')){
        $old="    \$mapped['quality_status']='review';\$mapped['quality_reason']='catalog_first_unresolved';\$resolved=[\$mapped];\n";
        $new=
            "    // LITTYWATCH_PHASE4E_INSUFFICIENT_IDENTITY\n".
            "    \$mapped['quality_status']='review';\n".
            "    \$__lwItem=mb_strtolower(trim((string)(\$mapped['item']??'')));\n".
            "    \$__lwInsufficient=(bool)preg_match('/^(?:axe|axes|shield|shields|staff|staves|scythe|scythes|sword|swords|hammer|hammers|spear|spears|wand|wands|dagger|daggers|focus|focus item|bow|bows|flatbow|flatbows|hornbow|hornbows|longbow|longbows|recurve bow|recurvebow|shortbow|shortbows|elite tome|elite tomes|normal tome|normal tomes)$/u',\$__lwItem);\n".
            "    \$mapped['quality_reason']=\$__lwInsufficient?'insufficient_item_identity':'catalog_first_unresolved';\n".
            "    \$resolved=[\$mapped];\n";
        $writer=rep1($writer,$old,$new,'writer unresolved');
        wr($writerFile,$writer);
    }

    json_decode((string)file_get_contents($dataFile),true,flags:JSON_THROW_ON_ERROR);
    foreach([$catalogFile,$resolverFile,$gateFile,$writerFile,$bundleFile] as $lint){
        $out=[];$code=0;exec('php -l '.escapeshellarg($lint).' 2>&1',$out,$code);
        if($code!==0) throw new RuntimeException("Syntaxcheck faalde {$lint}:\n".implode("\n",$out));
    }

    require $root.'/bootstrap.php';
    $db=db();
    new \LittyWatch\Parser\Catalog($root.'/app/Data',$db);

    echo "OK: LittyWatch V5.2 Phase 4E FIX1 geïnstalleerd.\n";
    echo "Backup: {$backupDir}\n";
    echo "Point identities:\n";
    $q=$db->prepare("SELECT key FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");
    foreach(['Party Points','Sweet Points','Alcohol Points'] as $name){
        $q->execute([$name]);$k=$q->fetchColumn();
        echo "  {$name}: ".($k?"OK [{$k}]":"NIET GEVONDEN")."\n";
    }
    echo "\nDraai daarna de volledige reparse opnieuw.\n";
} catch(Throwable $e){
    fwrite(STDERR,"ERROR: ".$e->getMessage()."\nRollback vanuit {$backupDir}...\n");
    foreach([$catalogFile,$resolverFile,$gateFile,$writerFile,$bundleFile] as $f){
        $b=$backupDir.'/'.basename($f); if(is_file($b)) @copy($b,$f);
    }
    exit(1);
}
