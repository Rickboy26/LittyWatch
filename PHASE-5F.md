# LittyWatch V5.2 — Phase 5F

Flow:

```bash
php tools/maintenance/phase5f/classify.php
php tools/maintenance/phase5f/mine-aliases.php
```

Controleer alias candidates. Daarna:

```bash
php tools/maintenance/phase5f/apply-aliases.php
php tools/maintenance/phase5e/resolve-groups.php
php tools/maintenance/phase5f/report.php
```

5F werkt alleen op resterende `keep_unresolved` groepen.
