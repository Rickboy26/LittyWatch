# LittyWatch V5.2 — Phase 3Z Context-Aware Candidate Pipeline

Phase 3Z moves multi-item understanding in front of the strict catalogue gate.

## Changes

- New `ContextAwareCandidatePipeline` turns explicit lists/bundles into independent candidates.
- Shared miniature context (`ded`/`unded`, `Celestial`) is inherited across list members.
- Compact slash lists such as `Zhed/Livia` can be recognized as miniature lists only when every member has an exact miniature catalogue identity.
- Tome and weapon-mod context can be inherited without treating numeric/stat slashes such as `20/20` or `5e/ea` as separators.
- Candidate resolution is no longer all-or-nothing: safe catalogue matches survive while unresolved meaningful candidates remain review-visible.
- Parser fallback rows with `no_catalog_item` that are later recovered by an exact/controlled catalogue match are promoted to `accepted / catalog_match`.
- Additional orphan fragments (`ran`, `nec`, `alc`, `sta`, `few mods`) are suppressed after failed catalogue recovery.
- Structured writer parser version bumped to `v5.2-phase3z-context-aware-candidate-pipeline`.

## Server tests

```bash
php tests/phase3z-context-aware-candidate-pipeline.php
php tests/phase3y-noise-mini-consumable-recovery.php
php tests/phase3x-contextual-offer-segmentation.php
php tests/phase3w-context-catalog-intelligence.php
php tests/parser-v5-structured.php
```

Only run `php tools/maintenance/reparse-all.php` after all tests pass.
