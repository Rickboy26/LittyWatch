# LittyWatch V4 Rewrite

Deze versie heeft één router, één layout en voor alle zichtbare hoofdonderdelen eigen controllers en views. De oude `app/Pages` frontend is verwijderd. Bestaande database, parser, repositories, cronjobs en onderhoudsfuncties zijn behouden.

## Installatie

Vervang de volledige repository-inhoud door deze versie. Bewaar vooraf `data/market.sqlite` en eventuele lokale assets.

## Hoofdroutes

`/`, `/live`, `/markets`, `/items`, `/traders`, `/trends`, `/intelligence`, `/watchlist`, `/alerts`, `/game-assets`, `/system`, `/admin`.

## Belangrijk

Dit is een frontend- en applicatiestructuur-rewrite bovenop de bestaande datalaag. Geen destructieve databasemigraties.
