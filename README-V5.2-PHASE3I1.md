# LittyWatch V5.2 — Phase 3I.1

Hotfix voor Phase 3I Market Outlier & Review Detection.

- Repareert twee fout geïnterpoleerde `trustedPriceExpr()` calls in `MarketRepository::itemSummary()`.
- Voorkomt `Undefined property: MarketRepository::$trustedPriceExpr` warnings.
- Herstelt de tellers voor bruikbare koop-/verkoopprijzen in de datakwaliteitskaart.
- Parser- en market-semantics uit 3H/3I blijven ongewijzigd.
