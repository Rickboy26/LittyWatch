# LittyWatch V5.2 — Phase 5K

Final deterministic leftovers.

Run:

```bash
php tools/maintenance/phase5k/classify.php
php tools/maintenance/phase5k/dry-run.php
```

Controleer candidates. Daarna:

```bash
php tools/maintenance/phase5k/apply-aliases.php
php tools/maintenance/phase5e/resolve-groups.php
php tools/maintenance/phase5k/report.php
```

Green shorthand blijft uitgesloten van automatische mapping.
