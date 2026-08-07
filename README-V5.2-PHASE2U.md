# LittyWatch V5.2 — Phase 2U

Production re-review synchronization fix.

- `ParserBatchReviewService` now constructs a fresh `ParserEngine` + `Catalog` directly from the currently deployed `app/Data` files for every re-review batch request.
- Removes dependency of the re-review maintenance path on the global `parserV2()` singleton.
- Keeps all Phase 2T fresh-Kamadan catalog and normalization rules.
- Adds `app/Data/parser-release.json` and displays `V5.2 Phase 2U` on Parser Review so the deployed parser revision is immediately verifiable.
- Batch JSON also reports the active parser release.

This phase specifically addresses the situation where direct Phase 2T parser tests succeeded while production re-review continued to emit the pre-2T review candidates.
