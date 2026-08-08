# LittyWatch V5.2 — Phase 3M5

## Automatische Gw.dat inventory-icon mapper

Phase 3M4 bewees dat alle 5277 inventory icons goed geïndexeerd worden. De oude beheerweergave telde vervolgens 5273 ongebruikte iconbestanden als “nog te koppelen”. Dat is misleidend: het assetpakket bevat alle game-icons, terwijl LittyWatch alleen voor zijn actuele marktitems een koppeling nodig heeft.

### Nieuwe itemcentrische koppellaag
- Nieuwe tabel `item_icon_links`: één marktitem heeft één gekozen lokaal inventory icon.
- Eenzelfde DAT-icon mag door meerdere marktitems worden gebruikt. Guild Wars bevat namelijk verschillende DAT-ID's en items die visueel hetzelfde icon kunnen delen.
- Bestaande Phase 3M4-koppelingen worden automatisch gemigreerd.
- `/item-image.php`, itemlijst en itemdetail kijken eerst naar deze nieuwe koppellaag.
- Een lokale Gw.dat inventory icon heeft voorrang op eerder gecachete Wiki-afbeeldingen.

### Automatische herkenning
Admin → Inventory icons bevat nu **Automatisch herkennen**.

De browser doet per nog ongekoppeld marktitem het volgende:
1. neemt de canonical/Wiki-titel uit LittyWatch;
2. vraagt bij Guild Wars Wiki alleen directe bestandsnamen zoals `File:Eternal Bow.png` en `File:Eternal Bow icon.png` op via MediaWiki CORS;
3. vergelijkt eerst de Wiki SHA1 met de 5277 lokale bestanden;
4. als de bestandshash niet gelijk is, vergelijkt hij een compacte visuele fingerprint met de lokale 64×64 icons;
5. alleen high-confidence matches (server-side minimaal 0,90) worden opgeslagen;
6. twijfelgevallen blijven ongemoeid voor handmatige review.

De Wiki wordt dus alleen tijdens de admin-herkenningsactie als herkenningsbron gebruikt. De spelerswebsite serveert daarna het lokale `itemIcon_<DAT-ID>.png` bestand.

### Beheerpagina opgeschoond
De statistieken zijn nu itemgericht:
- Inventory bestanden;
- geïndexeerde DAT-ID's;
- marktitems met icoon;
- marktitems zonder icoon.

De 5277 iconbestanden hoeven niet allemaal gekoppeld te worden. De handmatige DAT-browser blijft beschikbaar voor correcties en uitzonderingen.

### Fingerprint index
`assets/game-items/inventory-fingerprints.json` bevat voor alle 5277 lokale icons:
- DAT file ID;
- luminantie-dHash;
- alpha-dHash;
- gemiddelde alpha-gewogen RGB;
- bounding-box verhouding;
- lokale SHA1.

Hierdoor hoeft de browser niet duizenden lokale PNG's opnieuw in te lezen tijdens een mapper-run.

### Exchange-rate guardrail
De hoofdconfig blijft `100k ≈ 6 Ecto` als veilige fallback gebruiken. Ook de oude V2 fallback is gelijkgetrokken naar 6 Ecto, zodat een legacy codepad niet opnieuw 5/1 of 1/1 kan tonen.
