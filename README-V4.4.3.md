# LittyWatch V4.4.3 — Hard Split Fix

Opgelost:

- het `#lw-review-detail-panel` anker is uit queue-links verwijderd;
- de browser springt dus niet meer naar beneden bij het selecteren;
- de split-view staat nu als lokale scoped CSS rechtstreeks in de reviewpagina;
- oude of gecachte globale styles kunnen de layout niet meer overschrijven;
- `app.css` en `app.js` gebruiken cacheversie `v=443`.

Desktop:
- queue links;
- details rechts;
- beide met een eigen scrollbar.

Mobiel onder 760px:
- één kolom, zonder automatisch scrollen.
