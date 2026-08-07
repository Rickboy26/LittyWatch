# LittyWatch V5.2 Phase 3L.7 — Canonical Catalog Key Recovery

Phase 3L.7 fixes a key-normalization mismatch between structured offers and the static item catalog.

StructuredOfferWriter stores canonical item keys with underscores (for example `rift_warden`), while `app/Data/items.json` historically uses hyphens (`rift-warden`). Phase 3L.6 compared those keys literally, so catalog market semantics were missed for many multi-word items.

## Changes

- MarketQualityService now canonicalizes both structured offer keys and catalog keys to the same underscore representation before lookup.
- Bare-price recovery therefore works for known concrete items and catalog-declared stack quotes even when the stored offer key uses underscores.
- Existing safeguards for ranges, bundles, shared item lists and ambiguous commodity quotes remain unchanged.
- Parser/reparse version labels updated to Phase 3L.7.
- Regression coverage now tests realistic underscore item keys.

## Expected effect

Previously uncertain examples such as `Rift Warden 25a`, `ghostly hero 725a`, `Polar 100a`, `Four-Leaf Clover 15e` and `Lunar Fortune ... 20e` can now use their catalog semantics. Ambiguous examples such as `225-675e`, `Cupcakes / Eggs / Honeycombs 8e` and `GOTT STACK -27A/ 2-53A` stay uncertain.

## Deploy

```bash
git pull
php tools/maintenance/reparse-all.php
```

The reparse banner should say `LittyWatch Phase 3L.7 volledige reparse gestart`.
