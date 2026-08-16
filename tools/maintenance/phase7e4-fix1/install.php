<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Parser/Catalog.php';

if(!is_file($file)){fwrite(STDERR,"ERROR: Catalog.php ontbreekt.\n");exit(1);}
$code=file_get_contents($file);
if($code===false){fwrite(STDERR,"ERROR: Catalog.php lezen mislukt.\n");exit(1);}

$marker='LITTYWATCH_PHASE7E4_FIX1_RESIDUAL_ALIAS_DECORATOR';
if(str_contains($code,$marker)){
    echo "Phase 7E.4 FIX1 staat al in Catalog.php.\n";
    exit(0);
}

$backup=$root.'/storage/backups/phase7e4-fix1-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($file,$backup.'/Catalog.php');

/* Change items() to chain a residual alias decorator after 7E.2. */
$old="        return \$this->applyPhase7E2MiniatureAliases(\$this->items);";
$new=<<<'PHP'
        return $this->applyPhase7E4ResidualAliases(
            $this->applyPhase7E2MiniatureAliases($this->items)
        );
PHP;

if(!str_contains($code,$old)){
    copy($backup.'/Catalog.php',$file);
    fwrite(STDERR,"ERROR: items()-anchor niet gevonden.\n");
    exit(1);
}
$code=str_replace($old,$new,$code);

/* Insert decorator before modifiers(). */
$anchor="    public function modifiers(): array { return \$this->modifiers; }";
$pos=strpos($code,$anchor);
if($pos===false){
    copy($backup.'/Catalog.php',$file);
    fwrite(STDERR,"ERROR: modifiers()-anchor niet gevonden.\n");
    exit(1);
}

$method=<<<'PHP'

    /**
     * LITTYWATCH_PHASE7E4_FIX1_RESIDUAL_ALIAS_DECORATOR
     *
     * Curated high-confidence Kamadan shorthand. Only decorates already-existing
     * catalog records; it never creates new items.
     *
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private function applyPhase7E4ResidualAliases(array $items): array
    {
        static $cache = null;
        if ($cache !== null && count($cache) === count($items)) return $cache;

        $aliasesByCanonical = [
            'battle iced tea' => ['tea','teas','battle tea','iced tea','battle iced teas'],
            'party beacon' => ['beacon','beacons','party beacons'],
            'frostfire fang' => ['frostfire fang','frostfire fangs','frostfire f'],
            'margonite gemstone' => ['margo','margos','margonite gem','margonite gems'],
            'wintersday grab bag' => ['wd grab bag','wd grab bags','wintersday grab bags'],
            'little john' => ['little john'],
        ];

        foreach ($items as $index => $item) {
            if (!is_array($item)) continue;
            $name = mb_strtolower(trim((string)($item['name'] ?? '')));
            if (!isset($aliasesByCanonical[$name])) continue;

            $existing = $item['aliases'] ?? [];
            if (!is_array($existing)) $existing = [];

            $items[$index]['aliases'] = array_values(array_unique(array_merge(
                $existing,
                $aliasesByCanonical[$name]
            )));
        }

        return $cache = $items;
    }

PHP;

$code=substr($code,0,$pos).$method.substr($code,$pos);

if(file_put_contents($file,$code)===false){
    copy($backup.'/Catalog.php',$file);
    fwrite(STDERR,"ERROR: schrijven mislukt; backup teruggezet.\n");
    exit(1);
}

exec('/usr/bin/php -l '.escapeshellarg($file),$out,$rc);
if($rc!==0){
    copy($backup.'/Catalog.php',$file);
    fwrite(STDERR,"ERROR: syntaxfout; backup teruggezet.\n".implode("\n",$out)."\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.4 FIX1 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Mappings: Tea, Party Beacon, Frostfire Fang, Margonite Gemstone, Wintersday Grab Bag, Little John.\n";
echo "GHOTI/Celestal blijven unresolved. ALC nog niet gemapt.\n";
