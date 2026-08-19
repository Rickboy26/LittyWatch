# LittyWatch architecture

## Market ingestion
`collectMessages()` -> `ParserEngine` -> `StructuredOfferWriter` -> `structured_offers`

`structured_offers` is the only offer source of truth. The historical PHP parser/write path for the legacy `offers` table has been removed. Existing deployments may still contain an unused `offers` table; it is no longer read or written by this codebase.

## Price ownership
- `app/Parser/PriceMatcher.php`: price/quantity syntax recognition.
- `app/Parser/ParserEngine.php`: contextual market semantics and price interpretation.
- `app/Market/MarketQualityService.php`: post-parse quality/outlier validation.

## Catalog recovery
Catalog recovery lives under `app/Market/` with responsibility-based names:
- `ConservativeCatalogRecovery`
- `ConcreteClauseRecovery`
- `UniqueConcreteRecovery`
- `MiniatureRecovery`

## Runtime domains
The old `app/V2/` namespace has been retired. Active code now lives in normal domains (`Assets`, `Alerts`, `Encyclopedia`, `Intelligence`, `Search`, `Snapshots`, `Trader`, etc.).

## Compatibility
`cron/v2-intelligence.php` and `cron/v2-snapshot.php` are tiny forwarding shims for existing server crontabs. Point cron at `cron/market-intelligence.php` and `cron/snapshots.php`, then these two shims can be deleted.
