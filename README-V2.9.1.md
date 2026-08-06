# LittyWatch V2.9.1

- Installer-versie bijgewerkt naar V2.9.1.
- Healthpagina toont nu `Nog niet uitgevoerd` in plaats van `Geen statusbestand`.
- Healthpagina toont de exacte cronregel en SSH-testopdracht.
- `cron/test-alerts.php` toegevoegd als eenvoudige CLI-test.

## Cron

```cron
*/5 * * * * /usr/bin/php /var/www/hollandseglory.nl/public_html/cron/evaluate-alerts.php >> /var/www/hollandseglory.nl/public_html/logs/alerts-cron.log 2>&1
```
