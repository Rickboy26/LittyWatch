# LittyWatch 8.2 Architecture Cleanup

## Single source of truth
Market offers are parsed and stored only through:

`ParserEngine -> StructuredOfferWriter -> structured_offers`

The legacy `offers` write path and its bootstrap parser have been removed.

## Consolidated namespaces
The former `app/V2` runtime services were moved to domain namespaces: Alerts, Assets, Encyclopedia, Infrastructure, Intelligence, Search, Snapshots and Trader.

## Recovery components
Historical Phase7 class names were replaced with responsibility-based names:
- ConservativeCatalogRecovery
- ConcreteClauseRecovery
- UniqueConcreteRecovery
- MiniatureRecovery

Existing `quality_reason` strings are intentionally retained for database/history compatibility.

## Removed
- app/V2
- legacy saveOffers/extractPrice/detectQuantity/currencyToEcto
- legacy offers-table runtime reads/writes
- V2 diagnostic tools
- phase7e21-fix3 installer/smoke/verify package

## Validation
All PHP files lint successfully.
Tests that do not require SQLite pass except:
- parser-batch-transaction-wiring: pre-existing lifecycle/transaction wiring assertion
- parser-v5-structured: pre-existing Staff Wrapping typo regression

SQLite-dependent tests could not run in the build environment because pdo_sqlite is unavailable.
