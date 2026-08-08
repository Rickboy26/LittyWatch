# Phase 3Z.1 — Conservative Candidate Pipeline

Hotfix for the Phase 3Z reparse regression.

- Candidate splitting now prefers the parsed item and no longer scans arbitrary raw trade text.
- Raw-segment expansion is limited to miniature/tome umbrella rows.
- Every split candidate must have concrete catalogue evidence (exact item or unique alias).
- Expansion is transactional: if one candidate cannot resolve, the original parser row is retained.
- Generic weapon rows such as Axe/Staff/Shield are never expanded from punctuation-heavy raw text.

This prevents price, quantity, requirement and modifier fragments from becoming `catalog_first_unresolved` rows.
