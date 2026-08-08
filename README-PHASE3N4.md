# Phase 3N4 — Catalog-first item management

- De 2788 geïmporteerde GW1 records uit `kb_items` worden nu ook door AssetCatalogService als itemcatalogus gebruikt.
- De mislukte Wiki/GW-Market visual auto-matcher is uit de inventory-admininterface verwijderd.
- LittyWatch gokt geen DAT file ID uit een itemnaam: de publieke GW Market catalogus bevat itemnamen/assets, maar geen betrouwbare koppeling naar de numerieke `itemIcon_<DAT-ID>.png` bestanden uit jouw Gw.dat extract.
- Nieuwe veilige Knowledge Base cleanup verwijdert alleen exacte dubbele aliases. Gelijke zichtbare itemnamen worden gerapporteerd, niet automatisch samengevoegd.
- Handmatige DAT-koppelingen en bestaande curated mappings blijven behouden.
