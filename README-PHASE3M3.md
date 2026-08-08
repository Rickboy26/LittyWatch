# LittyWatch V5.2 · Phase 3M3

## Dashboard polish + echte inventory icons

Deze release rondt de publieke dashboard-opruiming af en corrigeert de exchange-rate normalisatie.

### Dashboard
- Nieuwe compacte premium dark/GW1 market styling.
- Vier hoofdkoersen: 100k/Ecto, Ecto/Armbrace, Ecto/Zkey en Ecto/Obsidian Shard.
- Live koersen gebruiken de mediaan per onafhankelijke trader uit geaccepteerde, trusted Kamadan-data.
- `100k = 1 Ecto` kan niet meer als geldige live koers door de normalisatie/sanity bounds glippen.
- Veilige fallback voor 100k/Ecto is 6 Ecto zolang er onvoldoende betrouwbare live samples zijn.
- Hardste stijger/daler vereist minimaal twee onafhankelijke traders in beide 24-uursvensters en vergelijkt dezelfde genormaliseerde marktvariant.
- Nieuwste aanbiedingen toont alleen geaccepteerde actieve offers en een robuuste marktmediaan.

### Inventory icons
- `item-image.php` gebruikt geen Guild Wars Wiki-thumbnails meer.
- Ondersteuning voor `item_icon_12345.png`, `itemIcon_12345.png` en `item-icon-12345.png`.
- Icons kunnen in `assets/game-items/` of submappen staan.
- Adminactie `/admin/assets-scan` indexeert reeds aanwezige icons en behoudt bestaande itemkoppelingen.
- Een plain ZIP met `item_icon_*.png` kan door `AssetCatalogService` worden verwerkt, ook zonder manifest.
- Optionele handmatige name -> DAT-id overrides kunnen in `config/item-icons.php`.

### Navigatie / beheer
Publieke hoofdnav: Dashboard, Items, Alerts. Admin is de enige beheer-ingang en bevat collectors, parserkwaliteit, dataset, knowledge, assets en systeemfuncties op één overzicht.
