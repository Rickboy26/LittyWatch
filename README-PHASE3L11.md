# LittyWatch V5.2 Phase 3L.11 — Stale Mixed-Basis Invalidation

- Mixed-basis bare quotes (GoTT/Tengu) now actively clear stale unit_price_ecto instead of only declining recovery.
- Prevents old stack-inferred units from surviving via SQL COALESCE and reappearing as market outliers.
- Explicit `ea`, `stack`, `/stk` and ratio syntax remains eligible for canonical recovery.
- Manual approvals are never invalidated.
