# LittyWatch V5.2 — Phase 3A/3B

## Doel
Phase 3A/3B maakt de Items-pagina en prijsstatistieken betrouwbaar door de legacy `offers`-tabel niet langer als bron voor de itemdirectory te gebruiken.

## Wijzigingen
- Items, itemdetails, actieve WTB/WTS, varianten en prijshistorie lezen nu uit `structured_offers`.
- Alleen `quality_status=accepted` en actieve structured offers tellen mee.
- Itemdirectory groepeert hoofdlettervarianten case-insensitive en sluit `Bundle:` pseudo-items uit.
- Prijsstatistieken gebruiken alleen expliciete `a`, `e` of `k` geldprijzen en negeren bundle/currency-exchange/unknown price bases.
- Volledige markt-reparse maakt een verse ParserEngine uit de werkelijk gedeployde `app/Data` bestanden.
- Admin-actie heet nu `Marktindex volledig herbouwen`.

## Na deploy
1. Open Beheer.
2. Klik **Marktindex volledig herbouwen**.
3. Laat de volledige bronset opnieuw parsen.
4. Controleer daarna Items opnieuw.

De oude `offers`-tabel blijft bestaan voor compatibiliteit, maar bepaalt de Items-pagina niet meer.
