# LittyWatch V5.2 — Phase 2C Parser Cleanup

Deze build richt zich op resterende Parser Review-ruis en contextproblemen.

## Wijzigingen

- Normaliseert `q 9` / `r 9` naar compacte requirements voordat segmentatie start.
- Verwijdert decoratieve runs zoals `----!!!!!` zonder betekenisvolle enkele stat-streepjes te slopen.
- Stript requirement-, quantity-, basis- en trade-ruis uit fallback item candidates.
- Onderdrukt pure noise-candidates zoals losse `q`, attributes/modifiers, `for all`, `each`, `PM offer` en alleen punctuatie.
- Expandeert kleine requirement-ranges voor generieke families, bv. `q5-7 flatbows` -> q5/q6/q7 Flatbow.
- Herkent meerdere requirement+attribute paren op hetzelfde context-item, bv. `q11 ES q13 FC q13 spaw`.
- Energy Storage shorthand `ES` toegevoegd als attribute.
- Veilige review-aliasnormalisaties toegevoegd voor `rez`, `zkey`, `Party Beaco`, `Sephis word`, `frog/froggy`.
- Meervoudsvormen toegevoegd aan generieke bow-family herkenning.

## Regressietests

Nieuwe `tests/parser-phase2c-review.php` plus bestaande Phase 2B, BDS/context, family-context, birthday, tomes, set-price, stack, barter, structured parser en knowledge-pack tests slagen.
