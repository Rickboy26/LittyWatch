# LittyWatch V3.2.1

Hotfix voor de One Site-router.

## Opgelost

- Alle pagina's onder `app/Pages/` bepalen de projectroot nu via `__DIR__`.
- Waarschuwingen over een ontbrekende `$root` zijn opgelost.
- De beheerpagina voor game-assets staat nu op `/game-assets`.
- `/assets` blijft exclusief de echte map voor CSS, JavaScript en afbeeldingen.
- Oude `/v3-assets.php`-bookmarks sturen door naar `/game-assets`.

Na uploaden eenmaal hard verversen met Ctrl+F5.
