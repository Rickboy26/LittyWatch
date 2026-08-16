<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$catalog=$root.'/app/Parser/Catalog.php';
$dataDir=$root.'/app/Data';
$dataFile=$dataDir.'/phase7e4-items.json';

if(!is_file($catalog)){fwrite(STDERR,"ERROR: Catalog.php ontbreekt.\n");exit(1);}
@mkdir($dataDir,0775,true);

$backup=$root.'/storage/backups/phase7e4-fix2-'.date('Ymd-His');
@mkdir($backup,0775,true);
copy($catalog,$backup.'/Catalog.php');
if(is_file($dataFile))copy($dataFile,$backup.'/phase7e4-items.json');

$payload=<<<'JSON'
[
  {
    "key": "battle-isle-iced-tea",
    "name": "Battle Isle Iced Tea",
    "category": "alcohol",
    "aliases": [
      "Battle Isle Iced Tea",
      "battle isle iced tea",
      "iced tea",
      "ice tea",
      "icetea",
      "tea",
      "teas",
      "battle tea"
    ]
  },
  {
    "key": "party-beacon",
    "name": "Party Beacon",
    "category": "festive",
    "aliases": [
      "Party Beacon",
      "party beacon",
      "beacon",
      "beacons",
      "party beacons"
    ]
  },
  {
    "key": "margonite-gemstone",
    "name": "Margonite Gemstone",
    "category": "special",
    "aliases": [
      "Margonite Gemstone",
      "margonite gemstone",
      "margonite gem",
      "margonite gems",
      "margo",
      "margos"
    ]
  },
  {
    "key": "wintersday-grab-bag",
    "name": "Wintersday Grab Bag",
    "category": "consumable",
    "aliases": [
      "Wintersday Grab Bag",
      "wintersday grab bag",
      "wintersday grab bags",
      "wd grab bag",
      "wd grab bags"
    ]
  },
  {
    "key": "frostfire-fang",
    "name": "Frostfire Fang",
    "category": "trophy",
    "aliases": [
      "Frostfire Fang",
      "Frostfire Fangs",
      "frostfire fang",
      "frostfire fangs",
      "frostfire f"
    ]
  },
  {
    "key": "little-john",
    "name": "Little John",
    "category": "unique",
    "aliases": [
      "Little John",
      "little john"
    ]
  }
]
JSON;
if(file_put_contents($dataFile,$payload)===false){
    fwrite(STDERR,"ERROR: phase7e4-items.json schrijven mislukt.\n");
    exit(1);
}

$code=file_get_contents($catalog);
$marker='LITTYWATCH_PHASE7E4_FIX2_CATALOG_ITEMS';

if(!str_contains($code,$marker)){
    $anchor=<<<'PHP'
        // LITTYWATCH_PHASE4H_FINAL_CATALOG
        $phase4hItemsPath = $dataDir . '/phase4h-items.json';
        if (is_file($phase4hItemsPath)) {
            $this->items = $this->mergeItems($this->items, $this->loadJson($phase4hItemsPath));
        }
PHP;

    if(!str_contains($code,$anchor)){
        fwrite(STDERR,"ERROR: Phase4H constructor-anchor niet gevonden.\n");
        exit(1);
    }

    $insert=$anchor.<<<'PHP'

        // LITTYWATCH_PHASE7E4_FIX2_CATALOG_ITEMS
        // Residual high-confidence market identities are loaded before the KB sync,
        // so CatalogFirstResolver and StrictCatalogGate see the same canonical rows.
        $phase7e4ItemsPath = $dataDir . '/phase7e4-items.json';
        if (is_file($phase7e4ItemsPath)) {
            $this->items = $this->mergeItems($this->items, $this->loadJson($phase7e4ItemsPath));
        }
PHP;

    $code=str_replace($anchor,$insert,$code);
    file_put_contents($catalog,$code);
}

exec('/usr/bin/php -l '.escapeshellarg($catalog),$out,$rc);
if($rc!==0){
    copy($backup.'/Catalog.php',$catalog);
    fwrite(STDERR,"ERROR: syntaxfout; Catalog.php teruggezet.\n".implode("\n",$out)."\n");
    exit(1);
}

echo "OK: LittyWatch V5.2 Phase 7E.4 FIX2 geïnstalleerd.\n";
echo "Backup: {$backup}\n";
echo "Data: app/Data/phase7e4-items.json\n";
echo "Toegevoegd/gesynchroniseerd:\n";
echo "  - tea/teas => Battle Isle Iced Tea\n";
echo "  - beacon(s) => Party Beacon\n";
echo "  - Margo => Margonite Gemstone\n";
echo "  - WD grab bag(s) => Wintersday Grab Bag\n";
echo "  - Frostfire Fang(s) => Frostfire Fang [nieuw catalog-item]\n";
echo "  - Little John => Little John [nieuw catalog-item]\n";
echo "GHOTI, Celestal en alc blijven bewust buiten deze fix.\n";
