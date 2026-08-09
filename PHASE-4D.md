# LittyWatch V5.2 — Phase 4D

Phase 4D targets the remaining high-confidence `catalog_first_unresolved` rows after 4C.

## Included

### Catalog reconciliation
Adds a small supplemental catalog loaded by `Catalog.php` for verified identities that were missing or present only under legacy/community names.

Important correction:
- `Miniature Undead Prince Rurik` -> **`Miniature Undead Prince`**

Also reconciles:
- Ghozer's Key
- Miniature Ghostly Hero
- Miniature Kuunavang
- Miniature Zhed Shadowhoof
- Miniature Rift Warden
- Miniature Ecclesiate Xun Rao
- Miniature Dagnar Stonepate
- several list-context miniatures
- Champion's Zaishen Strongbox
- Strategist's Zaishen Strongbox
- Zaishen Key

### List Grammar V2
Handles:
- `10.000 Party, 10.000 Alcohol, 10.000 Sweet`
- `Gold Unded Mini Shiro, Water Djinn, Zhu Hanuku, Black Beast, King Adelbern`
- `Naga/Oni/Shiro'ken Assassin/Vizu/Zhed`
- `Minis : Lich`
- `Ghostly Staffs Divine q9,10, Channel q9,10,12, Death q9,10 ...`

A single trailing bundle price is not copied to every miniature.

### Deliberately unchanged
Generic:
- Axe
- Shield
- Staff
- Sword
- Scythe
- Hammer
- Spear
- Wand
- Daggers
- Focus

stay unresolved when no concrete skin is present.

Generic `Elite Tome` / `Normal Tome` without a profession also stays unresolved.

## Install

Extract over the LittyWatch project root:

```bash
php tools/maintenance/install-phase4d.php
```

Then run the same complete reparse used after Phase 4C.

## Check

```bash
php -r '
require "bootstrap.php";

$sql="
SELECT lifecycle_status, quality_reason, COUNT(*) AS aantal
FROM structured_offers
GROUP BY lifecycle_status, quality_reason
ORDER BY aantal DESC
";
foreach (db()->query($sql) as $r) {
    printf("%-15s %-35s %d\n",
        $r["lifecycle_status"],
        $r["quality_reason"] ?? "-",
        $r["aantal"]
    );
}
'
```

Top unresolved:

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
