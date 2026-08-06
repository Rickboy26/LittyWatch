# LittyWatch V2.8 — Watchlist & koersalerts

## Nieuw

- Marktvariant kiezen uit bestaande Market Intelligence-data.
- Koopdoel: alert wanneer de laagste WTS onder het ingestelde bedrag komt.
- Verkoopdoel: alert wanneer de hoogste WTB boven het ingestelde bedrag komt.
- Watchlist-koersdoelen maken en onderhouden automatisch hun alerts.
- Alert-events hebben gelezen/ongelezen-status.
- Alerts triggeren alleen bij een nieuwe overgang of gewijzigde marktactiviteit.
- CLI-cron voor automatische evaluatie.

## Installatie

1. Upload alle bestanden met behoud van de mapstructuur.
2. Open eenmalig `/v2-alerts-install.php`.
3. Open `/v2-watchlist.php` en voeg een markt toe.
4. Stel optioneel een koop- en/of verkoopdoel in.

## Cron

Draai iedere vijf minuten:

```cron
*/5 * * * * /usr/bin/php /var/www/hollandseglory.nl/public_html/cron/evaluate-alerts.php >> /var/www/hollandseglory.nl/public_html/logs/alerts-cron.log 2>&1
```

Pas het PHP-pad of projectpad aan wanneer deze op jouw server anders zijn.

## Betekenis koersdoelen

- `Kopen bij max. ecto`: triggert als `best_wts_ecto <= doel`.
- `Verkopen bij min. ecto`: triggert als `best_wtb_ecto >= doel`.

## Terugzetten

De patch wijzigt alleen PHP-bestanden en voegt SQLite-kolommen toe. Zet voor code-rollback de vorige Git-versie terug. Extra SQLite-kolommen mogen blijven staan.
