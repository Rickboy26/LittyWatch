# LittyWatch V5.2 — Phase 3U Canonical Market Identity

Phase 3U maakt de catalogus de harde identiteit van de spelersmarkt.

## Regels

- Parser/community shorthand mag herkennen, maar wordt nooit als displaynaam opgeslagen.
- Accepted offers worden vóór opslag opnieuw naar één canonical catalog item vertaald.
- Bekende legacy-identiteiten worden naar hun echte GW1-naam hersteld, o.a.:
  - Silverwing Bow -> Silverwing Recurve Bow
  - Ghostly Hero -> Miniature Ghostly Hero
  - Rift Warden -> Miniature Rift Warden
  - Mallyx -> Miniature Mallyx
  - Kuuna/Kuunavang -> Miniature Kuunavang
  - Mad King's Guard -> Miniature Mad King's Guard
- Miniatures zonder ded/unded-context blijven review/rejected en komen niet op de spelersmarkt.
- Wiki-disambiguatienamen zoals `Recurve Bow (weapon)` worden niet als marktidentiteit geaccepteerd.
- Onopgeloste of ambigue catalog matches gaan naar review in plaats van `/items` of Dashboard.

## Deploy

Vanaf projectroot:

    php tools/maintenance/phase3u-canonicalize-catalog.php
    php tools/maintenance/reparse-all.php

Daarna optioneel nogmaals controleren:

    php -r 'require "bootstrap.php"; print_r((new \\LittyWatch\\Market\\StrictCatalogGate(db()))->quarantineExisting());'

## Tests

    php tests/phase3u-canonical-market-identity.php
    php tests/parser-v5-structured.php

`item-image.php` en de image resolver zijn in Phase 3U niet aangepast.
