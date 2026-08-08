# Phase 3X.2 — Uded list cleanup hotfix

Fixes the remaining miniature-list regression where `Uded` was detected as
state but was not removed from the first split candidate.

Expected:

`Uded Celestial Sheep and Rat`

becomes:
- `Miniature Celestial Sheep` with variant `unded`
- `Miniature Celestial Rat` with variant `unded`

Because Phase 3X resolves lists all-or-nothing, this also fixes the empty
`mini_list` result caused by the malformed first candidate.
