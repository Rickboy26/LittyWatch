# LittyWatch V5.2 — Phase 2G GW1 Catalog & Upgrade Parsing

Phase 2G targets the remaining concrete GW1 items, inscriptions/upgrades and generic modifier searches visible after Phase 2F.

## Catalog additions
- Firebrand
- Chunk of Drake Flesh
- Blessing of War
- Large Equipment Pack
- Ministerial Commendation
- Gold / Silver Zaishen Coin (+ GZC/SZC)
- Charr Bag
- Hero's Strongbox
- Party Beacon
- Red Rock Candy / Rainbow Candy Cane
- Battle Isle Iced Tea / Birthday Cupcake
- Perfect Salvage Kit
- Diessa Chalice
- Measure for Measure
- key inscriptions such as Strength and Honor, Live for Today, Dance with Death, Don't Think Twice, I have the power!, Master of My Domain, Aptitude not Attitude and Sheltered by Faith
- Shield Handle of Fortitude
- current mini shorthand for Madruk Dhuum, Forest Griffon and Wailing Lord

## Parser improvements
- `inscrib` is normalized the same as `insc/inscr/inscribable`.
- Common spelling errors such as `strenght & honor`, `Master fo my Domain`, `CrestedMachette` are normalized.
- `m4m`, `GZC`, `SZC`, `Red Rocks`, `Rainbows`, `Hero StrongBoxes`, `Primeval Remnants` resolve to concrete items.
- `beacon / tea / cake` expands into the three concrete consumables.
- Generic searches such as `Mods Soul Reaping +5`, `19% mods`, `Tormented Weapons`, generic gold-unid searches and alcohol-point stacks no longer pollute `no_catalog_item`.

## Regression coverage
Phase 2G and all relevant Phase 2B–2F parser regression tests pass.
