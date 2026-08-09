# LittyWatch V5.2 — Phase 4D.1

Fix voor de catalog split-brain: Parser\Catalog gebruikte JSON/knowledge-pack, terwijl CatalogFirstResolver en StrictCatalogGate alleen kb_items/kb_aliases controleerden.

4D.1 synchroniseert de volledig gemergede parsercatalogus non-destructief naar de KB voordat de markt-resolvers draaien.

Ook inbegrepen:
- `Miniature Undead Prince Rurik` -> `Miniature Undead Prince`
- cleanup voor `Alcohol Points Points`

Installeren:

```bash
php tools/maintenance/install-phase4d1.php
```

De installer controleert direct Zaishen Key, Miniature Ghostly Hero, Miniature Undead Prince en Ghozer's Key in `kb_items`.

Daarna:

```bash
php tools/maintenance/reparse-all.php
```
