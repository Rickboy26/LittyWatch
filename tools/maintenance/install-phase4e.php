<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$catalogFile  = $root . '/app/Parser/Catalog.php';
$resolverFile = $root . '/app/Market/CatalogFirstResolver.php';
$gateFile     = $root . '/app/Market/StrictCatalogGate.php';
$writerFile   = $root . '/app/Market/StructuredOfferWriter.php';
$bundleFile   = $root . '/app/Parser/MarketBundleExpander.php';
$dataFile     = $root . '/app/Data/phase4e-items.json';

foreach ([$catalogFile,$resolverFile,$gateFile,$writerFile,$bundleFile,$dataFile] as $required) {
    if (!is_file($required)) {
        fwrite(STDERR, "ERROR: vereist bestand ontbreekt: {$required}\n");
        exit(1);
    }
}

$stamp = date('Ymd-His');
$backupDir = $root . '/storage/backups/phase4e-' . $stamp;
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden gemaakt: {$backupDir}\n");
    exit(1);
}
foreach ([$catalogFile,$resolverFile,$gateFile,$writerFile,$bundleFile] as $file) {
    if (!copy($file, $backupDir . '/' . basename($file))) {
        fwrite(STDERR, "ERROR: backup mislukt voor {$file}\n");
        exit(1);
    }
}

function replace_once_4e(string $contents,string $needle,string $replacement,string $label): string {
    $count = substr_count($contents,$needle);
    if ($count !== 1) throw new RuntimeException("Anchor {$label} verwacht 1x, gevonden {$count}x.");
    return str_replace($needle,$replacement,$contents);
}
function write_4e(string $file,string $contents): void {
    if (file_put_contents($file,$contents) === false) throw new RuntimeException("Kon {$file} niet schrijven.");
}

try {
    // 1) Load synthetic market point identities before the 4D.1 KB sync.
    $catalog = (string)file_get_contents($catalogFile);
    if (!str_contains($catalog,'LITTYWATCH_PHASE4E_MARKET_IDENTITIES')) {
        $anchor = "        \$this->modifiers = \$this->loadJson(\$dataDir . '/modifiers.json');\n";
        $insert =
            "        // LITTYWATCH_PHASE4E_MARKET_IDENTITIES\n" .
            "        \$phase4eItemsPath = \$dataDir . '/phase4e-items.json';\n" .
            "        if (is_file(\$phase4eItemsPath)) {\n" .
            "            \$this->items = \$this->mergeItems(\$this->items, \$this->loadJson(\$phase4eItemsPath));\n" .
            "        }\n" . $anchor;
        $catalog = replace_once_4e($catalog,$anchor,$insert,'Catalog modifiers');
        write_4e($catalogFile,$catalog);
    }

    // 2) CatalogFirstResolver: canonical name first, stale key second.
    $resolver = (string)file_get_contents($resolverFile);
    if (!str_contains($resolver,'LITTYWATCH_PHASE4E_NAME_FIRST_EXACT')) {
        $old = <<<'OLD'
    private function catalogueExact(string $name,string $key): ?array
    {
        $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND (key=:k OR lower(trim(name))=lower(trim(:n))) ORDER BY CASE WHEN key=:k2 THEN 0 ELSE 1 END LIMIT 1");
        $st->execute([':k'=>$key,':k2'=>$key,':n'=>trim($name)]);$r=$st->fetch();
        return $r?['key'=>(string)$r['key'],'name'=>CanonicalMarketIdentity::nameFor((string)$r['name'],(string)$r['key'])]:null;
    }
OLD;
        $new = <<<'NEW'
    private function catalogueExact(string $name,string $key): ?array
    {
        // LITTYWATCH_PHASE4E_NAME_FIRST_EXACT
        // Reconstructed canonical identity outranks a legacy parser item_key.
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
NEW;
        $resolver = replace_once_4e($resolver,$old,$new,'CatalogFirstResolver catalogueExact');
    }

    if (!str_contains($resolver,'LITTYWATCH_PHASE4E_MINIATURE_QUARANTINE')) {
        $old = "        \$state=\$this->miniState(\$message.' '.\$item);\n        if(\$state===null)return []; // no ded/unded = review, never player market\n";
        $new =
            "        // LITTYWATCH_PHASE4E_MINIATURE_QUARANTINE\n" .
            "        \$segment=trim((string)(\$row['raw_segment']??''));\n" .
            "        if(preg_match('/\\b(?:potion|tonic)\\b/iu',\$segment) && !preg_match('/\\bmini(?:ature|pet)?s?\\b|\\b(?:uded|unded|undedicated|un[- ]?ded|ded|dedicated)\\b/iu',\$segment)){\n" .
            "            return [\$this->quarantineMiniature(\$row,'miniature_context_conflict')];\n" .
            "        }\n" .
            "        \$state=\$this->miniState(\$message.' '.\$item);\n" .
            "        if(\$state===null)return [\$this->quarantineMiniature(\$row,'miniature_variant_unresolved')];\n";
        $resolver = replace_once_4e($resolver,$old,$new,'miniature state gate');

        $helper = <<<'PHPHELPER'

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function quarantineMiniature(array $row,string $reason): array
    {
        $row['quality_status']='review';
        $row['quality_reason']=$reason;
        $row['confidence']=min((float)($row['confidence']??0.80),0.84);
        return $row;
    }
PHPHELPER;
        $anchor = "    private function normalizeMiniCandidate(string \$candidate): string\n";
        $resolver = replace_once_4e($resolver,$anchor,$helper."\n".$anchor,'normalizeMiniCandidate');
    }
    write_4e($resolverFile,$resolver);

    // 3) StrictCatalogGate: same name-first rule.
    $gate = (string)file_get_contents($gateFile);
    if (!str_contains($gate,'LITTYWATCH_PHASE4E_NAME_FIRST_GATE')) {
        $old = <<<'OLD'
        // Exact active KB key/name is authoritative.
        $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND (key=:key OR lower(trim(name))=lower(trim(:name))) ORDER BY CASE WHEN key=:key2 THEN 0 ELSE 1 END LIMIT 1");
        $st->execute([':key'=>$key,':key2'=>$key,':name'=>$name]);
        $row=$st->fetch();
OLD;
        $new = <<<'NEW'
        // LITTYWATCH_PHASE4E_NAME_FIRST_GATE
        // Exact canonical name outranks a stale legacy item_key.
        $row=false;
        if($name!==''){
            $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(:name)) LIMIT 1");
            $st->execute([':name'=>$name]);
            $row=$st->fetch();
        }
        if(!$row&&$key!==''){
            $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND key=:key LIMIT 1");
            $st->execute([':key'=>$key]);
            $row=$st->fetch();
        }
NEW;
        $gate = replace_once_4e($gate,$old,$new,'StrictCatalogGate exact lookup');
        write_4e($gateFile,$gate);
    }

    // 4) Compact miniature list shared-state inheritance.
    $bundle = (string)file_get_contents($bundleFile);
    if (!str_contains($bundle,'LITTYWATCH_PHASE4E_SHARED_MINI_STATE')) {
        $anchor = <<<'ANCHOR'
        // No explicit "mini" header: accept slash/comma lists only if every
        // member is a known miniature shorthand.
        $implicit = false;
ANCHOR;
        $replacement = <<<'REPL'
        // LITTYWATCH_PHASE4E_SHARED_MINI_STATE
        // Example: unded Naga/Oni/Shiro'ken Assassin/Vizu/Zhed
        if ($body === null && preg_match(
            '/^(?:wts|wtb|wtt)?\s*(unded(?:icated)?|ded(?:icated)?)\s+(.+[,\/].+)$/iu',
            trim($text),
            $sm
        )) {
            $state = $this->state($sm[1]);
            $body = trim($sm[2]);
        }

        // No explicit "mini" header: accept slash/comma lists only if every
        // member is a known miniature shorthand.
        $implicit = false;
REPL;
        $bundle = replace_once_4e($bundle,$anchor,$replacement,'MarketBundleExpander implicit mini list');
        write_4e($bundleFile,$bundle);
    }

    // 5) Separate insufficient identity from real catalog/parser backlog.
    $writer = (string)file_get_contents($writerFile);
    if (!str_contains($writer,'LITTYWATCH_PHASE4E_INSUFFICIENT_IDENTITY')) {
        $old = "    \$mapped['quality_status']='review';\$mapped['quality_reason']='catalog_first_unresolved';\$resolved=[\$mapped];\n";
        $new =
            "    // LITTYWATCH_PHASE4E_INSUFFICIENT_IDENTITY\n" .
            "    \$mapped['quality_status']='review';\n" .
            "    \$mapped['quality_reason']=\$this->isInsufficientIdentity((string)\$mapped['item'])?'insufficient_item_identity':'catalog_first_unresolved';\n" .
            "    \$resolved=[\$mapped];\n";
        $writer = replace_once_4e($writer,$old,$new,'StructuredOfferWriter unresolved mapping');

        $anchor = "  private function requirement(mixed \$v):?int{";
        $helper = <<<'HELPER'
  private function isInsufficientIdentity(string $item):bool{
   $n=mb_strtolower(trim($item));
   return (bool)preg_match('/^(?:axe|axes|shield|shields|staff|staves|scythe|scythes|sword|swords|hammer|hammers|spear|spears|wand|wands|dagger|daggers|focus|focus item|bow|bows|flatbow|flatbows|hornbow|hornbows|longbow|longbows|recurve bow|recurvebow|shortbow|shortbows|elite tome|elite tomes|normal tome|normal tomes)$/u',$n);
  }
HELPER;
        $writer = replace_once_4e($writer,$anchor,$helper."\n".$anchor,'StructuredOfferWriter requirement');
        write_4e($writerFile,$writer);
    }

    json_decode((string)file_get_contents($dataFile),true,flags:JSON_THROW_ON_ERROR);
    foreach ([$catalogFile,$resolverFile,$gateFile,$writerFile,$bundleFile] as $lintFile) {
        $out=[];$code=0;
        exec('php -l '.escapeshellarg($lintFile).' 2>&1',$out,$code);
        if($code!==0) throw new RuntimeException("PHP syntaxcheck faalde voor {$lintFile}:\n".implode("\n",$out));
    }

    require $root . '/bootstrap.php';
    $db=db();
    new \LittyWatch\Parser\Catalog($root . '/app/Data',$db);

    echo "OK: LittyWatch V5.2 Phase 4E geïnstalleerd.\n";
    echo "Backup: {$backupDir}\n";
    echo "Point identities:\n";
    $check=$db->prepare("SELECT key,name FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");
    foreach(['Party Points','Sweet Points','Alcohol Points'] as $name){
        $check->execute([$name]);$row=$check->fetch(PDO::FETCH_ASSOC);
        echo "  {$name}: ".($row?"OK [".$row['key']."]":"NIET GEVONDEN")."\n";
    }
    echo "\nNa reparse worden bewust rejected:\n";
    echo "  miniature_variant_unresolved\n";
    echo "  miniature_context_conflict\n";
    echo "  insufficient_item_identity\n";
    echo "\nDraai nu de volledige reparse opnieuw.\n";
} catch (Throwable $e) {
    fwrite(STDERR,"ERROR: ".$e->getMessage()."\n");
    fwrite(STDERR,"Rollback vanuit {$backupDir}...\n");
    foreach ([$catalogFile,$resolverFile,$gateFile,$writerFile,$bundleFile] as $file) {
        $backup=$backupDir.'/'.basename($file);
        if(is_file($backup)) @copy($backup,$file);
    }
    exit(1);
}
