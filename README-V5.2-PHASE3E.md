# LittyWatch V5.2 — Phase 3E: Canonical Price Normalization

Phase 3E fixes production price semantics at both parse-time and market-read time.

## Fixed

- `armbraces 27e x6` => 27e per item, quantity 6.
- `26e/ea x43` => 26e per item, quantity 43.
- `27e/ea 1750e = 64a` keeps the explicit 27e unit quote.
- Ambiguous Armbrace quotes such as `Arms 250e` do not contribute a trusted unit price.
- Cross-segment prices cannot be trusted for Armbrace statistics.
- Historical/stale pre-3D Armbrace rows are defensively filtered from item statistics when:
  - currency is not ecto;
  - stored unit price differs from the raw ecto amount;
  - raw Armbrace amount is implausibly above 100e.
- Suspicious stale rows remain visible as advertisements, but their displayed unit price is suppressed.

## Stack semantics

- `Royal Gifts 9a-stk` => 9a per 250-item stack => 0.972e per gift at 27e/a.
- `Royal Gifts 9a ea/stack` => 9a per stack => 0.972e per gift.
- `Royal Gift Stacks (x8) 8a` => 8a total for 8 stacks / 2000 gifts => 0.108e per gift.
- Structured market analytics now also exclude `unknown`, `currency_conversion`, `unqualified`, and `uncertain` price bases.

## Deploy

1. Deploy the files from this package.
2. Reload/restart PHP-FPM or clear OPcache if your server has timestamp validation disabled. This is important because Phase 3D production output showed behavior from stale parser code despite the source ZIP containing the fixed parser.
3. In LittyWatch Admin run **Marktindex volledig herbouwen** / full reparse.
4. Rebuild Market Intelligence if that is a separate maintenance action in your installation.

After the rebuild, Armbrace of Truth should no longer use 4.5e, 250e, 459e, 54e, or 0.83e as trusted market prices for the examples reported in production.
