# LittyWatch V5.2 — Phase 5I

Run:

```bash
php tools/maintenance/phase5i/classify.php
php tools/maintenance/phase5i/dry-run-items.php
php tools/maintenance/phase5i/dry-run-greens.php
```

Controleer eerst beide dry-runs.

Daarna alleen de item aliases toepassen:

```bash
php tools/maintenance/phase5i/apply-aliases.php
php tools/maintenance/phase5e/resolve-groups.php
php tools/maintenance/phase5i/report.php
```

Green shorthand is dry-run only.
