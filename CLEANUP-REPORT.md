# LittyWatch cleanup — V2.8.2

## Hersteld

- `v2-alerts-install.php` gebruikt nu de bestaande V2-autoloader en `LittyWatch\V2\Database`.
- SQLite-migraties voegen `updated_at` zonder dynamische default toe.
- Bestaande lege timestamps worden daarna veilig gevuld met `CURRENT_TIMESTAMP`.
- De V2-schema-installatie maakt de `structured_offers`-index alleen wanneer die tabel bestaat.
- Alle PHP-bestanden zijn opnieuw op syntax gecontroleerd.

## Verwijderd

- Ingepakte `.git`-geschiedenis.
- Dubbele/misplaatste GitHub Actions-workflows voor de geblokkeerde Wiki-import.
- Mislukte Wiki-403 patchbestanden en catalog-builder.
- Leeg tijdelijk bestand `MarketRepository.php.tmp`.
- Dubbele rootkopie van `StructuredMarketRepository.php`.
- Verouderde rootkopie van `StructuredMarketController.php`.
- Oude patch-, update- en versiehandleidingen; de actuele `README.md` en `README-V2.8.md` zijn behouden.

## Bewust behouden

- Installatie-, onderhouds- en diagnosetools die nog functioneel kunnen zijn.
- Tests en database-migraties.
- De huidige V1- en V2-applicatiecode, omdat beide nog door routes of onderhoudspagina’s gebruikt kunnen worden.
