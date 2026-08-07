# LittyWatch V5.2 — Phase 3G

## Catalog-driven default stack pricing

- Adds catalog-level `market_price_basis` and `market_stack_size` metadata.
- Candy Corn is configured as a 250-item stack market commodity.
- Explicit `15e/stk` remains explicit stack pricing.
- Bare `Candy Corn 20e` becomes 20e / 250 = 0.08e per item.
- Bare `Candy Corn 1a` becomes 27e / 250 = 0.108e per item.
- The rule is deliberately not applied to every consumable; `Conset 8e` remains unqualified unless the advertisement explicitly states its basis.
- Structured-offer parser version bumped to `v2.4-phase3g`.

After deployment, run `php tools/maintenance/reparse-all.php` once to rebuild historical structured offers.
