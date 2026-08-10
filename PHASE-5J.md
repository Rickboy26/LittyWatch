# LittyWatch V5.2 — Phase 5J

Laatste automatische cleanup vóór de green-shorthand fase.

Run:

```bash
php tools/maintenance/phase5j/classify.php
php tools/maintenance/phase5j/dry-run.php
```

Controleer candidates. Daarna:

```bash
php tools/maintenance/phase5j/apply-aliases.php
php tools/maintenance/phase5e/resolve-groups.php
php tools/maintenance/phase5j/report.php
```

Green shorthand wordt niet automatisch gemapt.
