# LittyWatch V5.2 — Phase 3W Context-to-Catalog Intelligence

Phase 3W haalt meer betekenis uit een Kamadan-segment **vóór** de Strict Catalog Gate. Het doel is meer echte catalogusoffers te schrijven zonder synthetische of generieke marktitems toe te laten.

## Nieuw

- **ControlledCatalogResolver**: één conservatieve resolver voor exacte aliases, compacte schrijfwijzen, typo's en context.
- **Upgrade intelligence**: upgrade-achtige shorthand wordt uitsluitend vergeleken met cataloguscategorieën voor upgrades, inscriptions en mods.
- `staff wrap enchanting` kan daardoor veilig naar `Staff Wrapping of Enchanting` wanneer dat de unieke catalogusmatch is.
- Shorthand zoals `vamp spear`, `crit spear`, `+5 SR staff wrapping` en `+5 SR wand wrapping` kan automatisch promoveren zodra de productiecatalogus één ondubbelzinnige upgrade-match bevat.
- **Controlled fuzzy matching**: tokenfouten, meervouden, compacte schrijfwijzen en kleine typo's worden alleen geaccepteerd bij een hoge score én voldoende voorsprong op kandidaat #2.
- **Segment-scoped context**: de resolver gebruikt bij voorkeur `raw_segment` en niet het volledige chatbericht, om cross-item contaminatie te voorkomen.
- **Generic quarantine uitgebreid**: `Wand`, `Staff`, `Bow`, `Shield`, `Tonic`, `Focus item`, `Spear`, enz. kunnen nooit zelf als marktitem door de Strict Catalog Gate.
- Miniature-regels uit 3U/3V blijven intact: een concrete miniature moet catalogus-backed zijn en vereist `ded`/`unded` voordat hij als marktobservatie wordt geaccepteerd.
- Parser release tag is bijgewerkt naar `v5.2-phase3w-context-catalog-intelligence`.

## Veiligheidsprincipe

Phase 3W maakt geen nieuwe itemnamen. De resolver mag alleen promoveren naar een reeds bestaand, actief `kb_items` record. Exacte alias-matches moeten uniek zijn. Fuzzy/context-matches moeten zowel een minimumscore als een duidelijke marge boven de tweede kandidaat hebben. Ambiguïteit blijft `catalog_first_unresolved` / review.

## Testen

```bash
php tests/phase3w-context-catalog-intelligence.php
php tests/parser-phase3v-context-aware.php
php tests/parser-v5-structured.php
php tests/phase3u-canonical-market-identity.php
php tests/phase3s-strict-catalog-gate.php
```

De meegeleverde Phase 3W database-test skipt alleen wanneer `pdo_sqlite` lokaal ontbreekt; op de LittyWatch-server hoort deze normaal te draaien.

## Deploy / reparse

Pak de patch over je huidige Phase 3V-installatie uit en voer daarna uit:

```bash
php tests/phase3w-context-catalog-intelligence.php
php tools/maintenance/reparse-all.php
```

Controleer daarna de resterende unresolved generics:

```bash
php -r '
require "bootstrap.php";
$sql="SELECT item, COUNT(*) aantal FROM structured_offers WHERE quality_reason=\"catalog_first_unresolved\" GROUP BY item ORDER BY aantal DESC LIMIT 50";
foreach(db()->query($sql) as $r) printf("%-45s %d\n", $r["item"], $r["aantal"]);
'
```

En controleer dat generieke marktitems niet actief zijn:

```bash
php -r '
require "bootstrap.php";
$sql="SELECT item, COUNT(*) aantal FROM structured_offers WHERE lifecycle_status=\"active\" AND lower(trim(item)) IN (\"wand\",\"staff\",\"bow\",\"shield\",\"tonic\",\"focus item\",\"spear\") GROUP BY item";
foreach(db()->query($sql) as $r) print_r($r);
'
```
