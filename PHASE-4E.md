# LittyWatch V5.2 — Phase 4E

Phase 4E is gebaseerd op de concrete `raw_segment` diagnose na 4D.1.

- Canonical name wordt vóór een mogelijk stale `item_key` geprobeerd.
- Miniatures zonder ded/unded blijven rejected met `miniature_variant_unresolved`.
- Potion/tonic-conflicten krijgen `miniature_context_conflict` en worden niet als miniature gepubliceerd.
- `unded Naga/Oni/Shiro'ken Assassin/Vizu/Zhed` erft `unded` naar alle lijstleden.
- Party/Sweet/Alcohol Points zijn expliciete synthetische marktidentiteiten.
- Kale weaponfamilies en generieke tomes blijven rejected, maar onder `insufficient_item_identity` in plaats van de echte parser-backlog.

Installeren:

```bash
php tools/maintenance/install-phase4e.php
```

Daarna volledige reparse:

```bash
php tools/maintenance/reparse-all.php
```
