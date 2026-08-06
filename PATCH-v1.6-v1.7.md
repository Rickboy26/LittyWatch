# LittyWatch v1.6 + v1.7

## v1.6 Parser review
- `/parser-review`: reviewqueue met filters.
- Goedkeuren, corrigeren of afkeuren van Parser-v2-resultaten.
- Verwachte item/q/attribute/market key opslaan.
- `/parser-review/export`: JSON-testcases voor latere regressietests.
- Legacy blijft leidend; deze tooling verandert geen bestaande offers.

## v1.7 Structured market pages
- `/markets`: marktvarianten op `market_key`.
- `/market?key=...`: mediaan, spread, prijsdata en advertenties per echte variant.
- BDS-markten worden gescheiden op q + attribute; OS-markten behouden relevante eigenschappen via de market key.

## Update
1. Upload/push de bestanden.
2. `git pull origin main` op de VPS.
3. Open één keer `/install.php`.
4. Na beschikbaarheid: `/reparse-v2.php`, daarna `/parser-review` en `/markets`.
