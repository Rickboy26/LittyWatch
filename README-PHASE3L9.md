# LittyWatch V5.2 Phase 3L.9 — Explicit Quote Precedence & Mixed-Basis Safety

Deze fase verfijnt de marktkwaliteit na Phase 3L.8.

- Expliciete `/ea`, `/each`, `/stk` en `/stack` quotes worden uit hun eigen bedrag omgerekend in plaats van een mogelijk stale `price_ecto` te hergebruiken.
- Bij meerdere alternatieven in één advertentie wint de eerste expliciete prijs in tekstvolgorde; latere bulkdeals overschrijven die niet.
- `stacks 22e/ea` blijft Kamadan-lotsemantiek: 22e per stack.
- Gift of the Traveler en Tengu Support Flare krijgen geen bare-price catalogusinferentie meer omdat live data zowel per item als per stack quoteert.
- Offers waarvan het uiteindelijke segment geen geldbedrag bevat maar wel een geërfde prijs heeft, worden niet langer trusted.
- Conset is expliciet per set/item gequote in de catalogus.
- Reparse/parserlabels zijn bijgewerkt naar Phase 3L.9.
