<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$catalogFile = $root . '/app/Parser/Catalog.php';
$identityFile = $root . '/app/Market/CanonicalMarketIdentity.php';
$bundleFile = $root . '/app/Parser/MarketBundleExpander.php';

foreach ([$catalogFile, $identityFile, $bundleFile] as $required) {
    if (!is_file($required)) { fwrite(STDERR, "ERROR: vereist bestand ontbreekt: {$required}\n"); exit(1); }
}

$stamp = date('Ymd-His');
$backupDir = $root . '/storage/backups/phase4d1-' . $stamp;
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: backupmap kon niet worden gemaakt: {$backupDir}\n"); exit(1);
}
foreach ([$catalogFile, $identityFile, $bundleFile] as $file) {
    if (!copy($file, $backupDir . '/' . basename($file))) { fwrite(STDERR, "ERROR: backup mislukt: {$file}\n"); exit(1); }
}

function repl(string $contents,string $needle,string $replacement,string $label): string {
    $count=substr_count($contents,$needle);
    if($count!==1) throw new RuntimeException("Anchor {$label} verwacht 1x, gevonden {$count}x.");
    return str_replace($needle,$replacement,$contents);
}
function putf(string $file,string $contents): void {
    if(file_put_contents($file,$contents)===false) throw new RuntimeException("Kon {$file} niet schrijven.");
}

try {
    $identity=(string)file_get_contents($identityFile);
    if(!str_contains($identity,'LITTYWATCH_PHASE4D1_UNDEAD_PRINCE')){
        $a="        'mad-kings-guard' => \"Miniature Mad King's Guard\",\n";
        $identity=repl($identity,$a,$a."        // LITTYWATCH_PHASE4D1_UNDEAD_PRINCE\n        'mini-undead-prince-rurik' => 'Miniature Undead Prince',\n",'Canonical BY_KEY');
        $a="        'mad kings guard' => \"Miniature Mad King's Guard\",\n";
        $identity=repl($identity,$a,$a."        'miniature undead prince rurik' => 'Miniature Undead Prince',\n        'undead prince rurik' => 'Miniature Undead Prince',\n",'Canonical LEGACY_NAMES');
        putf($identityFile,$identity);
    }

    $catalog=(string)file_get_contents($catalogFile);
    if(!str_contains($catalog,'LITTYWATCH_PHASE4D1_KB_CATALOG_SYNC')){
        $anchor="            \$this->knowledgeBase = new \\LittyWatch\\Knowledge\\KnowledgeBase(\$db);\n            \$dbItems = \$this->knowledgeBase->allItems();\n";
        $sync=<<<'CODE'
            $this->knowledgeBase = new \LittyWatch\Knowledge\KnowledgeBase($db);
            // LITTYWATCH_PHASE4D1_KB_CATALOG_SYNC
            // CatalogFirstResolver + StrictCatalogGate query the KB directly.
            // Mirror the fully merged parser catalog into those tables first.
            $syncByName = [];
            foreach ($this->items as $catalogItem) {
                if (!is_array($catalogItem)) continue;
                $catalogKey = trim((string)($catalogItem['key'] ?? ''));
                $rawName = trim((string)($catalogItem['name'] ?? ''));
                if ($catalogKey === '' || $rawName === '') continue;
                $canonicalName = \LittyWatch\Market\CanonicalMarketIdentity::nameFor($rawName, $catalogKey);
                $nameNorm = \LittyWatch\Knowledge\KnowledgeBase::normalize($canonicalName);
                if ($nameNorm === '') continue;
                $aliases = array_values(array_unique(array_filter(array_map(
                    static fn(mixed $a): string => trim((string)$a),
                    array_merge([$rawName, $canonicalName], $catalogItem['aliases'] ?? [])
                ))));
                if (!isset($syncByName[$nameNorm])) {
                    $syncByName[$nameNorm] = [
                        'key'=>$catalogKey,
                        'name'=>$canonicalName,
                        'category'=>(string)($catalogItem['category'] ?? 'unknown'),
                        'aliases'=>$aliases,
                    ];
                } else {
                    $syncByName[$nameNorm]['aliases'] = array_values(array_unique(array_merge(
                        $syncByName[$nameNorm]['aliases'], $aliases
                    )));
                }
            }
            $syncItem = $db->prepare("INSERT INTO kb_items(key,name,category_key,source,source_id,metadata_json,active,updated_at) VALUES(:key,:name,:category,'parser_catalog',NULL,'{}',1,:updated) ON CONFLICT(key) DO UPDATE SET name=excluded.name, category_key=CASE WHEN kb_items.category_key='' OR kb_items.category_key='unknown' THEN excluded.category_key ELSE kb_items.category_key END, active=1, updated_at=excluded.updated_at");
            $syncAlias = $db->prepare("INSERT OR IGNORE INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES(:item_key,:alias,:normalized,'parser_catalog')");
            foreach ($syncByName as $syncItemRow) {
                $syncItem->execute([
                    ':key'=>$syncItemRow['key'], ':name'=>$syncItemRow['name'],
                    ':category'=>$syncItemRow['category'], ':updated'=>gmdate('c'),
                ]);
                foreach ($syncItemRow['aliases'] as $syncAliasValue) {
                    $normalizedAlias = \LittyWatch\Knowledge\KnowledgeBase::normalize((string)$syncAliasValue);
                    if ($normalizedAlias === '') continue;
                    $syncAlias->execute([
                        ':item_key'=>$syncItemRow['key'], ':alias'=>(string)$syncAliasValue,
                        ':normalized'=>$normalizedAlias,
                    ]);
                }
            }
            $dbItems = $this->knowledgeBase->allItems();
CODE;
        $catalog=repl($catalog,$anchor,$sync,'Catalog KB init');
        putf($catalogFile,$catalog);
    }

    $bundle=(string)file_get_contents($bundleFile);
    if(!str_contains($bundle,'LITTYWATCH_PHASE4D1_POINTS_DEDUP')){
        $needle="        return count(\$out) >= 2 ? array_values(array_unique(\$out)) : null;\n";
        $replacement="        // LITTYWATCH_PHASE4D1_POINTS_DEDUP\n        \$out = array_map(static fn(string \$v): string => preg_replace('/\\b(Party|Sweet|Alcohol)\\s+Points\\s+Points\\b/iu', '\$1 Points', \$v) ?? \$v, \$out);\n        return count(\$out) >= 2 ? array_values(array_unique(\$out)) : null;\n";
        $bundle=repl($bundle,$needle,$replacement,'point-list return');
        putf($bundleFile,$bundle);
    }

    foreach([$catalogFile,$identityFile,$bundleFile] as $lintFile){
        $out=[];$code=0;exec('php -l '.escapeshellarg($lintFile).' 2>&1',$out,$code);
        if($code!==0) throw new RuntimeException("PHP syntaxcheck faalde voor {$lintFile}:\n".implode("\n",$out));
    }

    require $root . '/bootstrap.php';
    $db=db();
    new \LittyWatch\Parser\Catalog($root . '/app/Data',$db);

    $kbItems=(int)$db->query("SELECT COUNT(*) FROM kb_items WHERE active=1")->fetchColumn();
    $kbAliases=(int)$db->query("SELECT COUNT(*) FROM kb_aliases")->fetchColumn();
    echo "OK: LittyWatch V5.2 Phase 4D.1 geinstalleerd.\n";
    echo "Backup: {$backupDir}\nActieve KB items: {$kbItems}\nKB aliases: {$kbAliases}\nCatalog checks:\n";
    $check=$db->prepare("SELECT key,name FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");
    foreach(["Zaishen Key","Miniature Ghostly Hero","Miniature Undead Prince","Ghozer's Key"] as $name){
        $check->execute([$name]);$r=$check->fetch(PDO::FETCH_ASSOC);
        echo "  {$name}: ".($r?"OK [{$r['key']}]":"NIET GEVONDEN")."\n";
    }
    echo "\nDraai daarna de volledige reparse opnieuw.\n";
} catch(Throwable $e){
    fwrite(STDERR,"ERROR: ".$e->getMessage()."\nRollback code vanuit {$backupDir}...\n");
    foreach([$catalogFile,$identityFile,$bundleFile] as $file){$b=$backupDir.'/'.basename($file);if(is_file($b))@copy($b,$file);}
    exit(1);
}
