# LittyWatch V5.2 — Phase 3M4

## Dashboard cleanup + echte Gw.dat inventory icons

Deze build bundelt de dashboard/menu/admin-opruiming uit Phase 3M3 met het door de gebruiker aangeleverde Gw.dat assetpakket.

### Dashboard
- compacte exchange-rate kaarten voor 100k/Ecto, Ecto/Armbrace, Ecto/Zkey en Ecto/Obsidian Shard;
- live koers wordt alleen gebruikt uit geaccepteerde, actieve, trusted Kamadan-aanbiedingen van minimaal 2 onafhankelijke traders;
- `100k = 1 ecto` wordt door de plausibiliteitsgrens afgewezen; veilige fallback is `100k ≈ 6 ecto`;
- hardste stijger/daler vergelijkt twee 24-uursvensters en vereist onafhankelijke traders;
- nieuwste aanbiedingen toont buy/sell/trade, item, prijs, marktmediaan, speler en datum.

### Publieke navigatie
- Dashboard
- Items
- Alerts
- Admin

Watchlist is niet meer zichtbaar in de spelersinterface; bestaande interne route/data blijft voorlopig behouden voor backwards compatibility.

### Inventory icons
- 5277 originele `itemIcon_<DAT-ID>.png` bestanden uit het aangeleverde Gw.dat-pakket zijn fysiek meegeleverd;
- alle icons blijven lokaal: geen Wiki-thumbnails nodig;
- Dashboard, itemlijst en itemdetail gebruiken `/item-image.php` als één centrale resolver;
- vier dashboard-currencies hebben een directe lokale DAT-ID override;
- Admin → Inventory icons kan de hele map in één keer indexeren;
- `/game-assets` bevat nu een zoek/filter/grid-koppeltool om een DAT-ID handmatig aan een LittyWatch-item te koppelen;
- bestaande koppelingen blijven behouden wanneer de iconmap opnieuw wordt geïndexeerd;
- de scan gebruikt één database-transactie voor veel snellere verwerking van duizenden icons.
- gedeelde inventory-afbeeldingen worden per DAT-ID apart geïndexeerd; identieke pixels mogen dus niet langer verschillende file IDs samenvoegen.

### Belangrijk over het assetmanifest
Het aangeleverde extractor-manifest bevat 5277 icons, maar `linked_names = 0`: itemnaam en model-ID zijn voor deze extractor-fase nog leeg. LittyWatch koppelt daarom onbekende icons niet op goed geluk. Zodra een icon handmatig of door een toekomstige extractor aan een item is gekoppeld, wordt diezelfde lokale inventory icon overal op de website gebruikt.
