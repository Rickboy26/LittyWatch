# LittyWatch V5.2 — Phase 2F Catalog & Shared List Expansion

Phase 2F addresses the remaining high-impact parser failures after Phase 2E.

## Added
- Essential canonical items are seeded in the base parser catalog so matching does not depend on a runtime-published knowledge-pack file:
  - Trick-or-Treat Bag
  - Deldrimor Armor Remnant
  - Cloth of Brotherhood
  - Mysterious Armor Piece
  - Primeval Armor Remnant
  - Stolen Sunspear Armor
  - Clockwork Scythe
- Phase 2E hero-armor normalization is idempotent; repeated normalization no longer produces `Piece Piece` or `Remnant Remnant`.
- Shared comma-list expansion for messages such as:
  `q9 insc 2e/ea: WingedAxe, DualWingedAxe, HaloAxe`
  Every child inherits the shared requirement, inscribable modifier and price.
- ItemMatcher automatically accepts compact no-space forms of multi-word catalog aliases (for example `WingedAxe`, `GoldenMachete`, `RazorclawScythe`) without storing hundreds of duplicate aliases.
- Representative weapon skins are seeded for offline regression coverage; the full Wiki-generated knowledge pack continues to provide the broad catalog.

## Regression coverage
Phase 2F plus all relevant Phase 2B–2E, contextual variants, family context, tomes, birthday, stack notation, barter, set-price and V5 structured tests pass.
