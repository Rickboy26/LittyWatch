# LittyWatch Phase 4A — Known Entity Recovery + Weapon Context Intelligence

Phase 4A builds on the stable Phase 3Z.2 candidate pipeline.

## Pass 1 — Known Entity Recovery
Controlled exact rewrites for common GW trade shorthand. Rewrites are never authoritative by themselves: the target must resolve uniquely to an active catalogue name/alias.

Covered families include Gold Zaishen Coin shorthand, Zaishen Summoning Stone, Powerstone of Courage, Stygian Gem/Gemstones, Tanned Hide Square, Scale(s), Essence of Celerity, Ghozer's Key punctuation/price suffixes, and potion→tonic shorthand when the resulting tonic exists uniquely in the catalogue.

## Pass 2 — Weapon Context Intelligence
If the parser emits only a generic family (Axe/Shield/Staff/Sword/etc.), the resolver scans that offer clause for concrete active catalogue names/aliases. The longest unique family-compatible match wins.

Examples:
- `Shield` + `Eshield q9 Com` -> concrete shield only if `Eshield` is a unique catalogue alias.
- `Sword` + `crystalline q11 ...` -> Crystalline Sword only if `crystalline` uniquely identifies it.
- `Focus` + `Artifact flame q8 ...` -> concrete focus/artifact only if uniquely present in the catalogue.

A generic weapon with only requirement/stat information (for example `Axe q9`) remains unresolved. Equal-strength matches are treated as ambiguous and remain review data.

## Test

```bash
php tests/phase4a-known-entity-weapon-context.php
php tests/phase3z-context-aware-candidate-pipeline.php
php tests/phase3y-noise-mini-consumable-recovery.php
php tests/phase3x-contextual-offer-segmentation.php
php tests/phase3w-context-catalog-intelligence.php
php tests/parser-v5-structured.php
```

If all tests are green, run `php tools/maintenance/reparse-all.php` and inspect quality counts plus the unresolved top list.
