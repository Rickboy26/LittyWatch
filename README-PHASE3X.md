# LittyWatch V5.2 - Phase 3X Contextual Offer Segmentation

Phase 3X adds a conservative pre-catalog segmentation layer for explicit multi-item trader shorthand.

## What changed

- New `ContextualOfferListResolver` before the strict catalog gate.
- Splits explicit lists on commas, `and`, spaced `/`, `&`, and pipes while protecting GW stat notation such as `20/20` and price notation such as `5e/ea`.
- Inherits miniature state/family context for forms like `Uded Celestial Sheep and Rat`.
- Inherits weapon family context for mod lists such as `Zealous, Vamp Bow`.
- Uses raw segment context when the parser only produced an umbrella label such as `Weapon Mods`.
- Expansion is transactional at resolver level: if every meaningful part cannot resolve safely to a concrete active catalog item, the original offer remains unresolved/review instead of accepting a partial guess.
- Phase 3W test bootstrap fixed by registering the project autoloader.
- New Phase 3X regression test.

## Safety behaviour

This patch does not make generic `Axe`, `Staff`, `Shield`, etc. marketable. Generic bases remain blocked unless context resolves them to a concrete catalog identity. Noise tokens are not promoted. Miniatures still require explicit `ded`/`unded` state.

## Server checks

```bash
php tests/phase3w-context-catalog-intelligence.php
php tests/phase3x-contextual-offer-segmentation.php
php tests/parser-v5-structured.php
```

After those pass, reparse the corpus and compare `catalog_first_unresolved`, `strict_catalog_generic`, and `catalog_match` counts.
