# LittyWatch V5.2 — Phase 5G

Third-pass residual cleanup.

Run:

```bash
php tools/maintenance/phase5g/classify.php
php tools/maintenance/phase5g/dry-run.php
```

Controleer candidates. Daarna:

```bash
php tools/maintenance/phase5g/apply-aliases.php
php tools/maintenance/phase5e/resolve-groups.php
php tools/maintenance/phase5g/report.php
```

Geen parser-regex wordt gewijzigd.
