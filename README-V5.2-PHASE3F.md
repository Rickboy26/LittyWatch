# LittyWatch V5.2 Phase 3F — Maintenance Rebuild Timeout

Phase 3F fixes the full historical market rebuild timing out under PHP's default 30 second web request limit.

## Changes

- `MaintenanceController::reparse()` explicitly disables the execution time limit for the admin maintenance action when the PHP SAPI permits it.
- Added CLI fallback: `php tools/maintenance/reparse-all.php`.
- The CLI reparse processes messages in batches of 250 and prints progress, avoiding browser/PHP-FPM request timeouts.
- No parser price semantics changed in this phase; Phase 3E behavior is retained.

## Recommended production rebuild

From the project root:

```bash
php tools/maintenance/reparse-all.php
```

Then reload the relevant item pages. The normal web admin button can still be used, but CLI is preferred for large historical datasets.
