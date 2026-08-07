# LittyWatch V5.2 — Phase 2D Remaining Review Classifier

Phase 2D reduces false `no_catalog_item` review entries before catalog matching.

## Added
- `ReviewCandidateClassifier` separates concrete items from generic categories, services, point abstractions, modifier-only text and price fragments.
- Quote/parenthesis-aware grammar splitting. Ampersands inside names such as `"Strength & Honor"` are preserved.
- Generic `weapons & shields` phrases are kept together and suppressed as non-concrete item candidates.
- `want to buy` / `want to sell` normalize to WTB/WTS before offer splitting.
- Compact `ZKey1.3e` / `SZC0.6e` notation is normalized before matching.
- Defensive cleanup for duplicated learned `Deld... Hero armor Hero armor` labels.

## Regression coverage
Phase 2D, Phase 2C, Phase 2B, contextual variants, family context, mixed trade/tomes, birthday, stack notation, barter, set-price and V5 structured parser tests pass.
