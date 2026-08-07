# LittyWatch V5.2 Phase 3M.1 — Continuous Kamadan Collector

- Cron-safe one-shot collector: `php tools/maintenance/collect-kamadan.php`.
- Optional daemon/test mode: `--loop --interval=60`.
- Non-overlapping runs via `flock`.
- Up to 3 retries by default with short exponential backoff.
- Keeps original Kamadan row JSON in `messages.raw_payload`.
- Marks new rows with `collector_version=phase3m1`.
- Writes last-run status to `storage/kamadan-collector-status.json`.
- Cron helper: `bash tools/maintenance/install-kamadan-cron.sh`.
- Existing `source_key UNIQUE` + `INSERT OR IGNORE` remains the deduplication boundary.
