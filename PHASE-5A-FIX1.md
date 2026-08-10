# Phase 5A FIX1

Vervangt alleen `tools/maintenance/phase5a/build-queue.php`.

Verbeteringen:
- KB catalogus wordt één keer geladen.
- Exact name/alias lookup via indexes in geheugen.
- Fuzzy matching draait alleen op kandidaten met token overlap.
- Bestaande reviewbeslissingen blijven behouden.
- Bestaande 1.747 gedeeltelijk opgebouwde rows kunnen veilig opnieuw worden bijgewerkt.
- Voortgang elke 250 rows.
- Periodieke commits om lange SQLite write-locks te beperken.

Na uitpakken:

```bash
php tools/maintenance/phase5a/build-queue.php
php tools/maintenance/phase5a/report.php
```
