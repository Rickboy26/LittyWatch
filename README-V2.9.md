# LittyWatch V2.9 — Production Platform

## Nieuw
- Gedeelde V2.9 platformstyling en consistente versielabels.
- Systeem/health-dashboard op `/v2-health.php`.
- Cronstatusbestanden voor alerts en snapshots.
- Watchlist- en koersdoelen direct vanaf een markt-detailpagina.
- Featureflags voor snapshots, watchlists, alerts, traderprofielen en API geactiveerd.
- Productiefouten tonen een foutnummer; technische details gaan naar `logs/application.log`.

## Na upload
1. Open `/v2-alerts-install.php`.
2. Open `/v2-health.php`.
3. Stel cronjobs in voor `cron/evaluate-alerts.php` en `cron/v2-snapshot.php`.
4. Test een markt-detailpagina en sla koersdoelen op.
