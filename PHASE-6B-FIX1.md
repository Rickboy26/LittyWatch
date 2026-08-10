# Phase 6B FIX1

Fixes:
- isolate each `~` / `|` clause before extracting attribute
- prevent attribute leakage between clauses
- `sp` and `spaw` => Spawning Power
- explicit skin priority:
  - Bo => Bo Staff
  - Ghost => Ghostly Staff
  - Outcast => Outcast Staff
  - Plag => Plagueborn Staff
  - Jade => Jade Staff
- parent raw message may establish `staff` family for the whole tilde-list

Run:

```bash
php tools/maintenance/phase6b/dry-run.php
```

Do not apply until reviewing the new output.
