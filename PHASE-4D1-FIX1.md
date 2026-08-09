# Phase 4D.1 FIX1

Deze versie verwijdert de fragiele `MarketBundleExpander`-patch die op een dubbele return-anchor stukliep.

De hoofdoplossing blijft hetzelfde:
- synchroniseer de volledig gemergde parsercatalogus naar `kb_items`;
- synchroniseer aliases naar `kb_aliases`;
- forceer daarna direct een catalogus-build zodat de KB-sync meteen wordt uitgevoerd;
- controleer vier belangrijke identities.

Installeren:

```bash
php tools/maintenance/install-phase4d1.php
```

Verwachte checks:

```text
Zaishen Key: OK [...]
Miniature Ghostly Hero: OK [...]
Miniature Undead Prince: OK [...]
Ghozer's Key: OK [...]
```

Daarna:

```bash
php tools/maintenance/reparse-all.php
```
