# LittyWatch V4.0 — Refactor fase 1

Deze fase verandert bewust nog nauwelijks iets aan de zichtbare website. De
bedoeling is eerst een betrouwbare technische basis te leggen zonder bestaande
functionaliteit te breken.

## Wat is opgeschoond

- `app/bootstrap.php` bevat niet langer alle services en routes door elkaar.
- Services en controllers worden centraal geregistreerd via
  `ApplicationServiceProvider`.
- Webroutes en API-routes staan apart in:
  - `routes/web.php`
  - `routes/api.php`
- Paden worden centraal beheerd via `ProjectPaths`.
- Productiefouten worden afgehandeld via één `ErrorHandler`.
- `Application` doet alleen nog request dispatching en foutafhandeling.
- De losse featurepagina's gebruiken één gecontroleerde compatibiliteitslaag.

## Nieuwe hoofdstructuur

```text
app/
  Core/
  Controllers/
  Providers/
  Repositories/
  Services/
  Support/
  Views/
routes/
  web.php
  api.php
```

## Compatibiliteit

De routes en zichtbare functionaliteit uit V3.2.2 zijn behouden. De pagina's
onder `app/Pages` zijn nog niet volledig gemigreerd. Zij worden in fase 2 één
voor één opgesplitst in controller, service en view.

## Installeren

Vervang de huidige V3.2.2-code door de inhoud van deze ZIP. Behoud vooraf een
back-up van `data/`, `logs/`, `imports/` en eventuele lokale assetbestanden.

Er is geen nieuwe database-installatie nodig.

## Controle

Alle 131 PHP-bestanden zijn met `php -l` gecontroleerd.
