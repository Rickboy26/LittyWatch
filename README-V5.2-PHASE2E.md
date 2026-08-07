# LittyWatch V5.2 — Phase 2E Residual Item Resolution

Phase 2E targets high-confidence patterns from the remaining Parser Review after Phase 2D.

## Added
- Canonical community shorthand for Trick-or-Treat Bags, Clockwork Scythe and Eternal Bow shortbow notation.
- Hero-armor shorthand resolves to the concrete upgrade items: Deldrimor Armor Remnant, Cloth of Brotherhood, Mysterious Armor Piece, Primeval Armor Remnant and Stolen Sunspear Armor.
- Matching community aliases are added to the knowledge-pack alias seed and attach automatically when the generated Wiki catalog is present.
- Attribute-set searches such as `dom set` are classified as generic market intent rather than fake missing catalog items.
- Profession/weapon modifier searches such as `of the elementalist for scy` are kept out of `no_catalog_item` until the data model has a concrete upgrade match.
- No speculative mappings were added for ambiguous shorthand such as `Madr`, `BUs` or `Seals`.

## Deployment
No Wiki rebuild is required for the parser normalizations. Rebuilding/publishing the Knowledge Pack is recommended once if you want the newly seeded aliases persisted alongside the generated catalog.
