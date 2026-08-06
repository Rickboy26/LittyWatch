# LittyWatch v1.4 — Knowledge Profiles

Deze fase voegt Guild Wars-specifieke marktprofielen toe.

## Kernidee

Niet ieder item gebruikt dezelfde eigenschappen voor prijsvergelijking:

- **Bone Dragon Staff / staff skins:** requirement + attribute. Upgrade-mods worden genegeerd.
- **OS q8 weapons:** requirement + attribute + inherent mod + damage.
- **OS shields:** requirement + attribute + beide shieldmods + armor.
- **Miniatures:** dedicated/undedicated.
- **Commodities:** item + prijs per eenheid.

## Nieuwe onderdelen

- `kb_attributes`: genormaliseerde Guild Wars attributes en aliassen.
- `kb_profiles`: definities van relevante en genegeerde velden.
- `kb_item_profiles`: profieltoewijzing per item.
- `kb_category_profiles`: fallbackprofiel per categorie.
- `/knowledge`: browserpagina voor profielen, attributes en itemtoewijzingen.
- Parser v2 toont nu profiel, relevante eigenschappen en een `market_key`.

## Installatie

Open na deployment één keer:

`/knowledge-install.php`

Daarna testen via:

`/knowledge`

`/parser-v2-test.php`
