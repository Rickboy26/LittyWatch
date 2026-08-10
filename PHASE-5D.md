# LittyWatch V5.2 — Phase 5D Catalog-Assisted Residual Resolver

## 1. Dry-run
```bash
php tools/maintenance/phase5d/dry-run.php
```

Dit maakt een JSON-rapport onder `data/exports` en toont alleen sterke kandidaten.

## 2. Apply
Pas alleen HIGH + unieke kandidaten toe:

```bash
php tools/maintenance/phase5d/apply-high.php
```

## 3. Rapport
```bash
php tools/maintenance/phase5d/report.php
```

5D verandert geen parser-regex of catalogusbestanden. Het schrijft alleen reviewed outcomes in de 5B/5A reviewtabellen.
