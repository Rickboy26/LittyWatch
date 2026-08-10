# LittyWatch V5.2 — Phase 6B

Context-aware green resolver.

It uses:
- residual shorthand / skin
- attribute shorthand (`dom`, `illus`, `curs`, `spaw`, etc.)
- weapon family reconstructed from the same segment/raw Kamadan message
- existing kb_items catalogue

Run only the dry-run first:

```bash
php tools/maintenance/phase6b/dry-run.php
```

Only `strong_context` results are eligible for:

```bash
php tools/maintenance/phase6b/apply-strong.php
php tools/maintenance/phase5e/resolve-groups.php
php tools/maintenance/phase6b/report.php
```
