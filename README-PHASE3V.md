# LittyWatch V5.2 — Phase 3V Context-Aware Parser Intelligence

Phase 3V gebruikt de volledige Kamadan-zinscontext voordat losse itemkandidaten worden aangemaakt.

## Wat is verbeterd

- Negatie/exclusions: `unid golds (no scythes, shields or spears)` maakt alleen een Unidentified Gold-offer.
- Services: outpost runs, campaign tours, ferries/taxis en vergelijkbare transportservices worden vóór itemmatching uitgesloten.
- Context inheritance: `BDS q9 FC 35a | q11 Inspa 12a` houdt BDS vast voor de tweede variant.
- Variant lists: één canonical item met meerdere `q/attribute/price` clauses wordt opgesplitst naar afzonderlijke varianten.
- Packages: er wordt nooit meer een synthetisch `Bundle: A + B + C` item gemaakt. Elk echt catalogusitem blijft afzonderlijk zichtbaar, zonder de totaalprijs foutief per item toe te kennen.
- Miniatures: extra canonical normalisatie voor Ghostly Hero en Miniature Undead Prince Rurik.
- `Run for Your Life` blijft een itemcandidate en wordt niet door de serviceclassifier weggefilterd.

## Deploy

Pak deze patch over de bestaande Phase 3U-installatie uit. Daarna:

    php tests/parser-phase3v-context-aware.php
    php tests/parser-v5-structured.php
    php tests/parser-phase3l15-residual-noise-commodity-semantics.php

Daarna opnieuw reparsen:

    php tools/maintenance/reparse-all.php

Na de reparse kan Strict Catalog nogmaals worden uitgevoerd:

    php -r 'require "bootstrap.php"; print_r((new \LittyWatch\Market\StrictCatalogGate(db()))->quarantineExisting());'

## Belangrijk

Phase 3V wijzigt geen image-code of inventory assets. De patch-ZIP bevat uitsluitend gewijzigde/nieuwe parserbestanden, tests en deze README.
