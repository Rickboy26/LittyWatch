# LittyWatch v0.8 — Application foundation

Deze fase zet een onderhoudbare applicatiestructuur neer zonder de bestaande collector, database of parser te breken.

## Nieuw

- `app/Core`: Request, Response, Router, View, Container en Application.
- `app/Controllers`: dunne HTTP-controller.
- `app/Services`: dashboardlogica.
- `app/Repositories`: SQL en databasequeries op één plek.
- `app/Views`: herbruikbare layout en dashboardview.
- `public/index.php`: toekomstig veilig webroot-entrypoint.
- De bestaande root `index.php` blijft werken bij de huidige Apache-configuratie.

## Belangrijk

De oude `bootstrap.php` blijft tijdelijk de legacy-laag voor collector/parserfuncties. Nieuwe functies horen voortaan in classes onder `app/`. Hierdoor kan de migratie stap voor stap plaatsvinden zonder dat de live site uitvalt.
