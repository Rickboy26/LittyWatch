# LittyWatch V5.2 — Phase 2Q Catalog Disambiguation

Phase 2Q targets the remaining review queue after Phase 2P: 268 `catalog_match` and 6 `low_confidence` rows.

## Changes

- Adds message-level specificity filtering so learned generic rows such as Bow, Axe, Staff, Spear, Miniature, Focus item and Unique item can be suppressed even when their own raw segment contains only the generic token.
- Concrete catalog identities win over generic families/categories (for example Plagueborn Staff > Staff, Bogroot Focus > Focus item, Kuuna/Rift Warden/Dhuum > Miniature).
- Weapon-family tokens used as upgrade targets are not emitted as base-item observations (`+5 SR for Staff`, `Zealous for Spear`, `Bow/Axe/Spear Grip of Defense`, Staff Head, Bowstring, etc.).
- Legitimate generic market searches (Q8 Bows, Q9 Wands, Sword collections, White Minis) are promoted above the parser-review seed threshold when context is explicit.
- Exact canonical concrete catalog names receive strong identity confidence; generic family names remain context-scored.
- `Cons` only resolves to Conset in established stack-sale context (`Cons 9A/stack`), preventing barter phrases such as `Tengu Guard Cons 3:1` from becoming Conset.
- Modifier adjective shadows such as `Fiery` next to a concrete weapon skin are suppressed.

## Validation

- Phase 2B through Phase 2Q regression tests pass.
- 168 PHP files under `app` and `tests` pass `php -l`.
