# LittyWatch V5.2 — Parser Context & Catalog Expansion

## Fase 2A

- Context-aware parsing over `|`, `~`, `;` separated market segments.
- Inherits a weapon/item context for compact Kamadan listings.
- Expands multiple requirements such as `Q9/11` into separate market variants.
- Supports staff-family headers such as `Q9 Staves | Zodiac | Insectoid`.
- Adds common GW1 attribute shorthand including SR, FC, DF, Resto, Com, Inspi and Illu.
- Preserves miniature dedication as `dedicated` / `undedicated` metadata.
- Adds Xunlai Birthday Present 1st–7th Year and Xunlai Birthday Voucher to the durable catalog.
- Expands `BDay Present 1-7` into seven real items.
- Knowledge Pack consumables source now reads List of consumables, Sweet and Alcohol.
- Adds regression tests for BDS variants, staff-family context, birthday ranges and miniature dedication.
