# LittyWatch V5.2 – Phase 3K: Data Quality Workbench

Phase 3K maakt de 3J-kwaliteitsmetingen actiegericht.

## Nieuw

- `/admin/data-quality` als aparte Data Quality Workbench.
- Probleemgroepen zijn klikbaar vanuit Beheer.
- Stabiele categorieën:
  - `unpriced`
  - `uncertain`
  - `outlier`
  - `no_catalog_item`
  - `low_confidence`
  - `parser_review`
- Filters op categorie, WTB/WTS/WTT, zoekterm en aantal regels.
- Per kwaliteitsgeval zichtbaar:
  - item;
  - parserstatus en confidence;
  - ruwe prijs en unitprijs;
  - marktbaseline bij outliers;
  - speler en tijd;
  - originele advertentie/segment;
  - concrete parser- of prijsreden.
- Parser-reviewgevallen linken door naar Parser Review.
- Market-outliers worden als één probleemgroep geteld in plaats van per dynamische prijsreden.

Phase 3K wijzigt geen marktprijzen en corrigeert geen advertenties automatisch. Het is een diagnose- en prioriteringslaag bovenop 3J.
