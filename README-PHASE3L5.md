# LittyWatch V5.2 Phase 3L.5 — Safe Quality Promotion

Fixes the Phase 3L.4 gap where offer-level recovery populated `unit_price_ecto` but left `price_basis=uncertain`, causing MarketQualityService to classify the same row as uncertain again.

Safe explicit offer-level syntax now recovers both fields atomically:
- `2e/ea`, `2e each` -> basis `each`
- `50e/stk`, `50e/stack` -> basis `stack`, unit divided by 250
- `3:1e`, `3=1e` -> basis `ratio`
- explicit `price x quantity` inventory syntax -> basis `each`

Ranges, bundles and shared-list prices are not promoted.

After deploy run:
```bash
git pull
php tools/maintenance/reparse-all.php
```
The banner should say `LittyWatch Phase 3L.5 volledige reparse gestart`.
