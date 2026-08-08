# Phase 3P — Direct named inventory assets

De website hoeft voor het tonen van een inventory icon geen numerieke Gw.dat file-ID te kennen.

Flow:
1. De 2788 eerder geïmporteerde GW1 catalogusrecords leveren de itemnaam/categorie.
2. Admin haalt de bijbehorende publieke inventory PNG eenmalig op.
3. PHP valideert de PNG en bewaart hem lokaal onder `assets/game-items/named/<item-key>.png`.
4. `item_named_assets` legt de naam->lokale asset vast.
5. `item-image.php` gebruikt deze lokale named asset vóór de oude DAT-ID mapping.

Kamadan prijzen/trades worden niet overgenomen. De externe bron wordt niet tijdens normale pageviews benaderd.
