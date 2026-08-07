# LittyWatch V5.2 — Phase 2H Residual Review Cleanup

Phase 2H continues from Phase 2G and targets two review layers at once.

## no_catalog_item
- Adds concrete GW1/Kamadan residuals: Echovald Shield (`Echovald`), Cane, Hog's Gluttony (`Hog's Glut`), Map Set and Diessa Set.
- Adds safe shorthand for Stalker's Ration (`Stalkers`), Birdseye (`Birds Eye`), Magma's Shield residual spelling and Seal of the Dragon Empire (`Seals`).
- Treats generic upgrade/stat searches and price-context fragments as non-items instead of producing `no_catalog_item`.

## low_confidence / false catalog matches
- Bare generic `Tonic`, `Bowstrings`, `Inscriptions` and `Upgrades` are category searches, not concrete items.
- Imported one/two-character aliases can no longer create catalog matches.
- The community alias `Volta` no longer resolves to Voltaic Spear when the same segment explicitly describes a conflicting weapon family such as a shield, staff or wand.

## Weapon/stat notation
Concrete skin names such as Echovald Shield, Cane and Hog's Gluttony remain catalog items while existing requirement/attribute extraction (`q9`, `Tac`, `Str`, `Dom`, `Illu`) continues to provide variant metadata.
