# LittyWatch V4.0 — Refactor fase 2

Watchlist en Alerts zijn volledig uit de tijdelijke `app/Pages`-compatibiliteitslaag gehaald.

## Nieuwe structuur

- `app/Controllers/WatchlistController.php`
- `app/Controllers/AlertController.php`
- `app/Services/WatchlistService.php`
- `app/Services/AlertService.php`
- `app/Repositories/WatchlistRepository.php`
- `app/Repositories/AlertRepository.php`
- `app/Views/watchlist/index.php`
- `app/Views/alerts/index.php`

## Verwijderd

- `app/Pages/watchlist.php`
- `app/Pages/alerts.php`

## Verder aangepast

- `/watchlist` en `/alerts` hebben nu eigen GET- en POST-routes.
- De alert-cron gebruikt dezelfde nieuwe service als de website.
- Beide schermen gebruiken de centrale V4-layout en één thema.
- Bestaande tabellen en gegevens blijven behouden; er is geen destructieve migratie.

## Volgende refactorfase

Traders en traderdetail worden daarna gemigreerd naar dezelfde architectuur.
