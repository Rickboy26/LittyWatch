# LittyWatch tools

Only reusable operational tools live here. Historical phase installers and one-off patch scripts are intentionally removed; Git history is the archive.

## Maintenance

- `maintenance/collect-kamadan.php` — production CLI collector with locking/retries.
- `maintenance/reparse-all.php` — full structured-offer reparse.
- `maintenance/rebuild-lifecycle.php` — rebuild offer lifecycle state.
- `maintenance/expire-active-offers.php` — expire stale active offers.
- `maintenance/prune-market-data.php` — retention cleanup (explicit `--apply`).
- `maintenance/reset-live-market.php` — destructive live-market reset (explicit `--execute`).
- `maintenance/reject-impossible-variants.php` — apply current variant validity rules to accepted offers.
- `maintenance/export-training-dataset.php` — export parser training data.
- `maintenance/ai-queue.php`, `maintenance/ai-validate.php` — AI validation utilities.

## Reports

Read-only diagnostics are in `reports/`.

## Other

- `v2-check.php`, `v2-schema-check.php` — V2 compatibility/schema diagnostics.
- `wiki-knowledge-build.php` — optional Guild Wars Wiki knowledge importer.
