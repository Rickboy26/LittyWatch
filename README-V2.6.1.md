# LittyWatch V2.6.1 — Intelligence Rebuild Hotfix

## Probleem

De V2.2 intelligence-engine vertrouwde op `lifecycle_status = active`.
Door eerdere maintenance-logica kon vrijwel alles als `superseded` of `expired`
zijn gemarkeerd. Daardoor bleef soms maar één markt over.

## Oplossing

De rebuild:

- gebruikt alle geaccepteerde structured offers;
- vertrouwt lifecycle-status niet meer;
- kiest zelfstandig de nieuwste offer per:
  - trader;
  - market_key;
  - WTB/WTS-zijde;
- behoudt markten zonder prijs;
- filtert extreme prijsuitschieters pas vanaf vijf samples;
- toont extra diagnostische aantallen in de JSON-uitkomst.

## Na deployment

Open:

`/v2-intelligence-refresh.php`

Daarna:

`/v2-intelligence.php`

en:

`/v2-hub.php`

Er is geen installer of reparse nodig.
