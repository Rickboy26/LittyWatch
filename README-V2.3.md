# LittyWatch V2.3 — Market Explorer

Deze update bouwt voort op V2.2 Market Intelligence.

## Nieuw

- `/v2-markets.php` — zoekbare en filterbare marktverkenner.
- `/v2-market.php?key=...` — detailpagina met intelligence, actieve offers en snapshots.
- Watchlist toevoegen/verwijderen vanaf de detailpagina.
- 30-dagen SVG-prijsgrafiek op basis van `market_snapshots`.
- `/api/v2-markets.php` — JSON API voor marktindex en detaildata.
- Ecto- én armbraceweergave op alle nieuwe marktkaarten.

## Installatie

Geen migratie nodig. V2.1 en V2.2 moeten al aanwezig zijn.

Herbereken eventueel eerst:

- `/v2-intelligence-refresh.php`
- `/v2-snapshot.php`

Daarna:

- `/v2-markets.php`
- klik een marktvariant aan

## Commit

```bash
git add .
git commit -m "feat(v2.3): add market explorer detail pages and public JSON API"
git push origin main
```
