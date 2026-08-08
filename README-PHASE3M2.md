# LittyWatch V5.2 — Phase 3M2 Dashboard & Navigation Cleanup

## Dashboard
- Compacte automatische exchange-rate kaarten voor Platinum/Ecto, Ecto/Armbrace, Ecto/Zkeys en Ecto/Obsidian Shards.
- Koersen gebruiken betrouwbare Kamadan-data van de laatste 7 dagen wanneer minimaal twee bruikbare quotes bestaan; anders blijft de bestaande configuratie als fallback actief.
- Hardste stijger en hardste daler vergelijken de mediane betrouwbare prijs van de laatste 24 uur met de voorgaande 24 uur, met minimaal twee samples per periode.
- Nieuwste aanbiedingen tonen offer type, item, prijs, gemiddelde betrouwbare itemprijs in ecto, speler en datum/tijd.
- Oude dashboard-metrics, flip-kansen en technische statusblokken zijn uit de publieke dashboardweergave gehaald.

## Navigatie
Publiek menu:
- Dashboard
- Items
- Alerts

Beheer:
- Eén centrale Admin-ingang.
- Technische pagina's blijven intern bestaan om bestaande functionaliteit niet te breken, maar zijn uit het spelersmenu verwijderd.
- Admin bevat links naar collectors, dataset, reparsing, market maintenance, parser review/lab, quality workbench, knowledge base/pack, game assets en systeemstatus.
