# LittyWatch V5.2 Phase 3I — Market Outlier & Review Detection

Phase 3I separates parser correctness from market-price trust.

## New structured-offer fields

- `price_quality_status`: `trusted`, `uncertain`, `outlier`, `unpriced`
- `price_quality_reason`
- `price_outlier_score`
- `price_baseline_ecto`

## Behaviour

- Accepted offers can remain visible while their price is excluded from market stats.
- Ambiguous monetary offers with no trusted unit price are queued for Parser Review.
- Statistical outlier detection starts only with at least 5 usable samples from at least 3 traders.
- Outliers require a very large deviation from the robust item median; normal illiquid spreads are retained.
- Manual approval in Parser Review promotes an uncertain/outlier price back to trusted.
- Item quality cards now show registered offers, usable prices, uncertain prices and review count.
- Full reparse and market maintenance rebuild price quality after lifecycle processing.

## Deploy / rebuild

```bash
cd /var/www/hollandseglory.nl/public_html
php tools/maintenance/reparse-all.php
```
