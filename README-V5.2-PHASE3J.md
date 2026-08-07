# LittyWatch V5.2 – Phase 3J: Data Quality Cleanup & Market Trust

Phase 3J maakt de kwaliteit van de marktdata meetbaar en zichtbaar zonder nieuwe parser-special-cases toe te voegen.

## Nieuw

- Beheer toont dataset-health: totale offers, trusted prices, unpriced, parser review, uncertain prices en outliers.
- Kwaliteitsproblemen worden gegroepeerd op concrete reden.
- Market Trust per item combineert:
  - bruikbare prijsdekking;
  - onafhankelijke traders;
  - samplegrootte;
  - penalties voor uncertain/outlier prices.
- Itempagina toont Market confidence met score, label, prijsdekking, traders en flags.
- Beheer toont de zwakste actieve markten zodat parser/knowledge-work gericht kan worden geprioriteerd.

## Trust labels

- 85–100: Zeer sterk
- 70–84: Sterk
- 50–69: Redelijk
- 30–49: Zwak
- 0–29: Zeer zwak

Phase 3J verandert geen bestaande trusted-prijssemantiek. Het meet de uitkomst van 3D–3I en maakt zwakke plekken zichtbaar.
