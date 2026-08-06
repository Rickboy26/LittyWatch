# LittyWatch V2.7.1 — Wiki 403 Hotfix & Catalog Foundation

## Opgelost

De Wiki-client gebruikt nu:

1. MediaWiki API;
2. normale HTML-pagina als fallback bij HTTP 403;
3. uitgebreidere browserachtige headers;
4. een aparte image-download met referer en acceptheaders.

## Catalogusimport

Nieuwe pagina:

`/v2-catalog-import.php`

Start met:

`Category:Items`

Let op: `Category:Items` bevat vooral subcategorieën. De importer slaat die op en
kan vervolgens één of twee niveaus diep importeren.

De Guild Wars Wiki noemt `Category:Items` een lijst van alle items. De relevante
handelscategorieën zijn onder andere:

- Category:Weapons
- Category:Miniatures
- Category:Currencies
- Category:Crafting materials
- Category:Keys
- Category:Stackable items
- Category:Rare items

## Installatie

Open één keer:

`/v2-catalog-install.php`

Daarna:

`/v2-catalog-import.php`

## Veilig importeren

Begin met maximaal één niveau diep. Sommige categorieën hebben honderden pagina's.
Importeer grote categorieën apart om time-outs te voorkomen.

## Bestaande itempagina

De knop Wiki synchroniseren gebruikt automatisch de HTML-fallback wanneer api.php
HTTP 403 teruggeeft.
