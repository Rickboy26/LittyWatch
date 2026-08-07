# LittyWatch V5.2 Phase 3L.8 — Stack-lot semantics + comparable baselines

- Explicit `stack/stacks` wording now wins over misleading `/ea` parser state: `Lockpick stacks 22e/ea` means 22e per stack, not per lockpick.
- Availability markers (`x15`, `18x`, `50x`) no longer become the stack divisor.
- Mixed bulk offers such as `Gott Stacks (x10) 26a/each 10=250a` prefer the explicit 26a-per-stack quote; the bulk total stays secondary.
- Market outlier baselines are grouped by `normalized_market_key` rather than only `item_key`, reducing invalid comparisons between equipment variants/requirements.
- Parser/reparse labels bumped to Phase 3L.8.
