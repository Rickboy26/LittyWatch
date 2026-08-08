# Phase 3Q — Direct source catalogue + inventory assets

Deze build verwijdert de fragiele tussenstap waarbij LittyWatch eerst zijn eigen
`kb_items` schema moest uitlezen om icons te importeren.

De import gebruikt nu rechtstreeks dezelfde publieke bron voor beide kanten:
- `server/data/<category>.json` -> itemnamen
- `assets/items/<category>/<Item_Name>.png` -> bijbehorend inventory icon

Daarmee is de koppeling naam -> icon deterministisch vanuit de bronstructuur.

Het icon wordt daarna lokaal opgeslagen in:
`assets/game-items/named/<littywatch-item-key>.png`

`item-image.php` serveert die lokale named asset als primaire bron. Kamadan
aanbiedingen/prijzen blijven volledig LittyWatch-data.

De oude Wiki image matcher en de GWCA runtime-route zijn niet meer nodig voor
normale iconweergave.
