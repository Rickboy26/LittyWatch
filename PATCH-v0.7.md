# LittyWatch v0.7 — Knowledge Base foundation

- SQLite knowledge-base tables for items, aliases, categories, groups and import runs.
- Seeds the current parser catalog into the database.
- Adds group expansion, including `eternal skins (bow, sword, staff, shield, chaos axe)`.
- Adds a generic JSON importer for a future/public GW Market catalog feed.
- Adds a safe GW Market discovery page that inspects public HTML/JS for API candidates.

## Upgrade
1. Upload/commit all files.
2. Open `/knowledge-install.php` once.
3. Test `/parser-v2-test.php` with the eternal-skins example.
4. Open `/gwmarket-discover.php`, run discovery, and share the JSON output.

This release does **not** claim to scrape or copy the complete GW Market catalog yet. The exact public data endpoint must first be discovered and verified.
