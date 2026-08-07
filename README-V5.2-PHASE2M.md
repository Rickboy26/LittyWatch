# LittyWatch V5.2 — Phase 2M Item Taxonomy

Phase 2M introduces a parser-facing item taxonomy instead of relying only on flat aliases.

## Added
- `app/Data/taxonomy.json`: hierarchy and roles for weapons, caster weapons, shields, upgrades, miniatures, tonics, consumables, materials, trophies, currencies, presents, tomes, hero armor and other market families.
- `ItemTaxonomy`: distinguishes concrete inventory items from generic families, professions, quantity context, dedication context and market-action fragments.
- Confidence scoring now uses the taxonomy rather than a hard-coded generic-item list.
- Residual catalog coverage for Golden Egg, Jadeite Summoning Stone (`Turtle Stones`) and Charr Carving.
- Normalization for `Hero Box` / `herobox`, bare Rin/Diessa list shorthand and DSR identity confidence.

## Review cleanup
Prevents fallback review rows for orphan fragments such as professions (`mesmer`, `necro`), `stacks`, `or 1a`, `Trade`, dedication markers and `Cons GoM for EoC/AoS` barter context.

Specific catalog identities continue to outrank generic item families.
