# LittyWatch V5.2 — Phase 5H

Run:

```bash
php tools/maintenance/phase5h/classify.php
php tools/maintenance/phase5h/dry-run.php
```

Controleer candidates. Daarna:

```bash
php tools/maintenance/phase5h/apply-aliases.php
php tools/maintenance/phase5e/resolve-groups.php
php tools/maintenance/phase5h/report.php
```

Green/unique shorthand wordt alleen gerapporteerd, niet automatisch gemapt.
