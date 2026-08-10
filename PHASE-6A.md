# LittyWatch V5.2 — Phase 6A

GW1 Green/Unique Knowledge Resolver.

## Flow

```bash
php tools/maintenance/phase6a/install.php
php tools/maintenance/phase6a/dry-run.php
```

Controleer de dry-run. Alleen `strong_unique` mappings mogen worden toegepast:

```bash
php tools/maintenance/phase6a/apply-strong.php
php tools/maintenance/phase5e/resolve-groups.php
php tools/maintenance/phase6a/report.php
```

Phase 6A schrijft geen parser-regex. Het verrijkt alleen de learned aliaslaag.
