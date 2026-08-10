# LittyWatch V5.2 — Phase 5E Trusted Market Alias Learning

Flow:

```bash
php tools/maintenance/phase5e/install.php
php tools/maintenance/phase5e/dry-run.php
```

Controleer de alias-candidates. Daarna:

```bash
php tools/maintenance/phase5e/apply-aliases.php
php tools/maintenance/phase5e/resolve-groups.php
php tools/maintenance/phase5e/report.php
```

5E schrijft learned aliases alleen naar een aparte tabel en verandert geen parser-regex.
