# Refactorrapport

## Afgerond in fase 1

1. De centrale bootstrap is teruggebracht van een groot configuratiebestand
   naar een klein samenstelpunt.
2. Serviceconstructie is uit de routeconfiguratie gehaald.
3. API-routes zijn gescheiden van webpagina's.
4. Bestandslocaties worden niet meer verspreid met `dirname()` berekend in de
   nieuwe architectuurlaag.
5. Foutlogging en foutweergave zitten niet meer in `Application` zelf.
6. De compatibele featurepagina-controller valideert expliciet welke pagina's
   geladen mogen worden.

## Nog bewust niet verwijderd

- `app/Pages/*`: deze bevatten nog HTML, queries en POST-afhandeling door
  elkaar. Verwijderen zonder migratie zou functies breken.
- `app/V2/*`: hier zit nog werkende domeinlogica achter traders, alerts,
  intelligence, snapshots en assets.
- `api/v2-*.php`: oude compatibiliteitsendpoints. Deze worden pas verwijderd
  nadat is gecontroleerd dat er geen externe clients meer op leunen.

## Volgende refactorfase

Migratievolgorde:

1. Watchlist + alerts
2. Traders + traderdetail
3. Intelligence + trends
4. Live feed
5. Assets + systeemstatus

Per onderdeel wordt de huidige `app/Pages/*.php` vervangen door:

```text
Controller -> Service -> Repository -> View
```

Daarna kunnen de oude featurepagina's en steeds meer `app/V2`-compatibiliteit
veilig worden verwijderd.
