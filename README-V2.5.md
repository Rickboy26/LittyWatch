# LittyWatch V2.5 — Alerts & Live Feed

## Nieuwe onderdelen

- `/v2-alerts-install.php` — maakt `alerts` en `alert_events`
- `/v2-alerts.php` — beheer website-alerts
- `/v2-live.php` — automatisch verversende live market feed
- `/api/v2-live.php` — live-feed API
- `/api/v2-alerts.php` — alerts/events API
- `/cron/v2-alerts.php` — controleert alertregels

## Alerttypen

- WTS onder een ectodrempel
- WTB boven een ectodrempel
- spread boven een ectodrempel
- nieuwe marktactiviteit

## Installatie

Open één keer:

`/v2-alerts-install.php`

Daarna:

`/v2-alerts.php`
`/v2-live.php`

## Optionele cron

```cron
*/5 * * * * /usr/bin/php /var/www/hollandseglory.nl/public_html/cron/v2-alerts.php >> /var/www/hollandseglory.nl/public_html/logs/v2-alerts.log 2>&1
```

De live feed vernieuwt alleen wat al in de database staat. De Kamadan-collector moet dus afzonderlijk periodiek blijven draaien.
