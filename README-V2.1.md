# LittyWatch V2.1 — Watchlists & Market Snapshots

Deze update bouwt verder op de werkende V2.0.1-fundering.

## Nieuw

- `/v2-watchlist.php` — handmatige watchlist met actuele WTB/WTS-prijzen.
- `/v2-snapshot.php` — maakt één marktsnapshot voor maximaal 250 actieve markten.
- `/v2-trends.php` — vergelijkt snapshots over de laatste 7 dagen.
- `cron/v2-snapshot.php` — CLI-versie voor periodieke snapshots.
- Veilige V2-databasehelper met schema-validatie.

## Installatie

Geen nieuwe installer nodig. De pagina's maken ontbrekende tabellen en indexen automatisch aan.

## Aanbevolen cronjob (later)

```cron
*/15 * * * * /usr/bin/php /var/www/hollandseglory.nl/public_html/cron/v2-snapshot.php >> /var/www/hollandseglory.nl/public_html/logs/v2-snapshots.log 2>&1
```

## Testvolgorde

1. `/v2-snapshot.php`
2. `/v2-watchlist.php`
3. voeg een bestaande `market_key` toe
4. `/v2-trends.php`

Na één snapshot is er nog geen echte trend. Daarvoor zijn minimaal twee snapshots op verschillende momenten nodig.
