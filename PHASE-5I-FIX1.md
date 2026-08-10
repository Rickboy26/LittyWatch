# Phase 5I FIX1

Fix:
- bare `Shards -> Obsidian Shard` verwijderd omdat dit te ambigu is.
- overige item-candidates ongewijzigd.
- green resolver blijft dry-run only.

Na uitpakken:

```bash
php tools/maintenance/phase5i/dry-run-items.php
```

Verwacht: 4 veilige candidates uit de vorige run.
