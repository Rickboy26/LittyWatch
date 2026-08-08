# LittyWatch V4.1

Zie `README-V4.1.md` voor de parserfix en de vernieuwde itemdetailpagina.

# LittyWatch V4 Rewrite

Deze versie heeft één router, één layout en voor alle zichtbare hoofdonderdelen eigen controllers en views. De oude `app/Pages` frontend is verwijderd. Bestaande database, parser, repositories, cronjobs en onderhoudsfuncties zijn behouden.

## Installatie

Vervang de volledige repository-inhoud door deze versie. Bewaar vooraf `data/market.sqlite` en eventuele lokale assets.

## Hoofdroutes

`/`, `/live`, `/markets`, `/items`, `/traders`, `/trends`, `/intelligence`, `/watchlist`, `/alerts`, `/game-assets`, `/system`, `/admin`.

## Belangrijk

Dit is een frontend- en applicatiestructuur-rewrite bovenop de bestaande datalaag. Geen destructieve databasemigraties.

## Phase 3M3
Zie `README-PHASE3M3.md` voor dashboard polish, correcte exchange-rate normalisatie en lokale GW1 inventory icons.


## Phase 3M4
De build bevat nu 5277 lokale Gw.dat inventory icons, een DAT-ID koppelbeheerder en de opgeschoonde spelersinterface. Zie `README-PHASE3M4.md`.

## Phase 3M5
Automatische high-confidence item → Gw.dat inventory-icon herkenning, itemcentrische iconstatistieken en lokale icons met voorrang op Wiki-cache. Zie `README-PHASE3M5.md`.
