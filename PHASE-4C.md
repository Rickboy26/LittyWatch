# LittyWatch V5.2 — Phase 4C

Phase 4C is gericht op de resterende `catalog_first_unresolved`-groepen na Phase 4B.

## Wat 4C doet

### 1. Miniature list/context pass
- Herkent headers zoals `minis`, `miniatures`, `unded minis`, `ded minipets`.
- Laat die context vóór een kale naam-match winnen.
- Voorbeelden:
  - `Unded minis | Ghostly Hero | Zhed | Kuuna | Rift Warden`
  - `ded minis | Prince Rurik, Bone Dragon`
- Gerichte aliases:
  - `Ghostly Hero` -> `Miniature Ghostly Hero` **alleen met mini/ded/unded-context**
  - `Zhed` -> `Miniature Zhed Shadowhoof`
  - `Kuuna` -> `Miniature Kuunavang`
  - `Rift Warden` -> `Miniature Rift Warden`
  - `Prince Rurik` / `Undead Prince Rurik` -> `Miniature Undead Prince Rurik`

Een kale naam buiten miniature-context wordt dus niet blind geforceerd.

### 2. Bundle resolver
- `Party/Sweet/Alcohol` en varianten -> drie echte point-markten.
- `iron and dust` -> `Iron Ingot` + `Pile of Glittering Dust`.
- `Powerstones/Stygian Gemstones` -> `Powerstone of Courage` + `Stygian Gemstone`.
- Profession-tomebundles zoals:
  - `1500 Elite tomes (250x ele, ranger, war, mes, nec, mo) 140a`
  worden opgesplitst naar de echte profession-tomes.

De globale `140a` uit zo'n tomebundle wordt bewust **niet** aan elke profession gekopieerd; dat zou prijsvervuiling geven.

`300+ Elite / Normal Tomes` zonder beroepen blijft unresolved. Dat is expres: er is onvoldoende informatie om echte catalogusitems te kiezen.

### 3. Exact alias cleanup
- `Ghozer´s Key` / `Ghozer's Key for 300e` -> `Ghozer's Key`
- `Gold Flames` -> `Golden Flame of Balthazar`
- `AcientHornbow` -> `Ancient Hornbow`
- `SR+4 Spea` / `SR+5 Spear` -> `Spear Grip of the Necromancer`
- `Bow ES+5` -> `Bow Grip of the Elementalist`
- `+30HP` wordt alleen naar `... of Fortitude` omgezet wanneer de componentfamilie expliciet aanwezig is.
- Oude `Stygian Gem` normalisatie wordt gecorrigeerd naar `Stygian Gemstone`.

## Wat 4C nadrukkelijk niet doet

Generieke `Axe`, `Shield`, `Staff`, `Bow`, enz. worden niet naar een willekeurige skin vertaald. Als de raw clause echt geen concrete skin bevat, blijft die marktinformatie bewust onvoldoende.

## Installeren

Pak de zip uit over de LittyWatch projectroot en voer uit:

```bash
php tools/maintenance/install-phase4c.php
```

De installer:
- maakt eerst backups onder `storage/backups/phase4c-YYYYmmdd-HHMMSS`;
- patcht alleen bekende anchors;
- voegt `app/Parser/MarketBundleExpander.php` toe;
- draait `php -l` op alle gewijzigde PHP-bestanden;
- rolt terug als een stap faalt.

## Daarna

Draai dezelfde volledige reparse die je na Phase 4B gebruikte. Controleer daarna:

```bash
php -r '
require "bootstrap.php";

$sql="
SELECT
    lifecycle_status,
    quality_reason,
    COUNT(*) AS aantal
FROM structured_offers
GROUP BY lifecycle_status, quality_reason
ORDER BY aantal DESC
";

foreach (db()->query($sql) as $r) {
    printf(
        "%-15s %-35s %d\n",
        $r["lifecycle_status"],
        $r["quality_reason"] ?? "-",
        $r["aantal"]
    );
}
'
```

En specifiek de resterende 4C-doelen:

```bash
php -r '
require "bootstrap.php";

$sql="
SELECT item, COUNT(*) AS aantal
FROM structured_offers
WHERE lifecycle_status = \"rejected\"
  AND quality_reason = \"catalog_first_unresolved\"
GROUP BY item
ORDER BY aantal DESC
LIMIT 80
";

foreach (db()->query($sql) as $r) {
    printf("%-45s %d\n", $r["item"], $r["aantal"]);
}
'
```
