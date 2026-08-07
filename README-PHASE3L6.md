# LittyWatch V5.2 Phase 3L.6 — Catalog-Aware Bare Price Recovery

Phase 3L.6 reduces the remaining `uncertain` market-quality cases without weakening safety.

## What changed

- MarketQualityService now reuses catalog-owned quote semantics for a final accepted offer segment with exactly one bare money quote.
- Explicit `market_quote_basis=stack` items are normalized using their catalog quote size.
- Explicit `market_quote_basis=each` items are normalized per item.
- Concrete non-commodity catalog items mirror ParserEngine's existing convention and are normalized per item.
- Ambiguous shared lists, numeric ranges, bundles/packages and multi-price segments remain uncertain.

## Examples now recoverable

- `Rift Warden 25a` -> 675e/item
- `ghostly hero 725a` -> 19575e/item
- `MALLYX 50e` -> 50e/item
- `Cupcakes 8e` -> 0.032e/item (catalog stack quote)
- `Lunar Fortune ... 20e` -> 0.08e/item
- `Four-Leaf Clover 15e` -> 0.06e/item

## Deliberately still uncertain

- `Soup 50e`
- `Compasses 20e`
- `Red Rock Candy 225-675e`
- `Cupcakes / Eggs / Honeycombs 8e`
- `GOTT STACK -27A/ 2-53A`

## Deploy

```bash
git pull
php tools/maintenance/reparse-all.php
```

The reparse banner should say `LittyWatch Phase 3L.6 volledige reparse gestart`.
