# Phase 3Y — Noise Suppression + Mini/Consumable Recovery

- Drops only proven orphan trade/quantity fragments before unresolved rows are written.
- Normalizes miniature trader syntax such as `Mini's X`, `X mini`, and `Minis: X`.
- Keeps the existing safety rule: miniatures still require `ded`/`unded` before they become market data.
- Normalizes typographic apostrophes and trailing `/ price` syntax for exact/alias recovery.
- Adds exact expansion for `Gold zc` -> `Gold Zaishen Coin`; catalog existence remains authoritative.
- Does not relax the Strict Catalog Gate and does not guess generic weapon bases.
