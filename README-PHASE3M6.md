# LittyWatch V5.2 — Phase 3M6

## Inventory mapper: reliable Wiki image bridge

Phase 3M5 could process all market items but returned zero matches. The local 5277-icon index and database were healthy; the weak point was reading/comparing the external Wiki PNG in the browser.

### Changes
- Added `GET /game-assets/wiki-icon?file=File:...png`.
- The endpoint only accepts PNG file names and only retrieves files from Guild Wars Wiki's `Special:Redirect/file` path.
- cURL is used when available, with `allow_url_fopen` as fallback.
- Retrieved Wiki inventory icons are cached under `storage/cache/wiki-inventory-icons`.
- The browser matcher first reads the Wiki icon through the LittyWatch same-origin endpoint; direct CORS loading remains a fallback.
- Visual matching remains conservative but is less brittle for differences in alpha/transparency produced by different Gw.dat extraction pipelines.
- Final mapper output now reports:
  - Wiki file candidates found;
  - images successfully read;
  - how many were read through the LittyWatch proxy vs direct;
  - image read failures;
  - unresolved items.

### Safety
The Wiki is still only used during the admin recognition action. A successful mapping stores the local DAT file ID and the public website serves `/assets/game-items/inventory/itemIcon_<id>.png`.
