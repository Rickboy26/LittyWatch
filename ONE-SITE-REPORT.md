# One-site cleanup

- Eén publieke entry: `index.php`.
- Alle functionele pagina's lopen via de router.
- Oude `v2-*.php` en `v3-assets.php` zijn uit de publieke root verwijderd.
- Oude URL's krijgen permanente redirects.
- Eén navigatie en één platformstylesheet.
- Installers/diagnosepagina's zijn uit productie verwijderd.
- Onderhoud staat onder `tools/maintenance`, cronjobs onder `cron`.

Publieke rootbestanden: .gitignore, .htaccess, README.md, bootstrap.php, config.php, index.php, item-image.php
