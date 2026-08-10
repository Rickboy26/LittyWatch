# Phase 5E FIX1

Voorkomt dat zuivere canonical spelling/punctuation-correcties als reusable learned alias worden opgeslagen.

Specifiek wordt `Not in the face -> Not the face!` uit de 5E alias candidates gehouden.

Installatie:

```bash
php tools/maintenance/phase5e/install-fix1.php
php tools/maintenance/phase5e/dry-run.php
```

Verwacht: 8 candidates in plaats van 9.
