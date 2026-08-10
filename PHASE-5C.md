# LittyWatch V5.2 — Phase 5C Automatic Group Decisions

Phase 5C automatiseert de 5B group review.

## Veiligheidsprincipe
- alleen harde patroonregels;
- exact één catalogussuggestie met score 1.0 mag automatisch `correct_item` worden;
- broad-list/prenerf/set/package guards blokkeren automatische acceptatie;
- twijfelgevallen worden `keep_unresolved`, niet gegokt.

## Uitvoeren

```bash
php tools/maintenance/phase5c/auto-decide.php
php tools/maintenance/phase5c/report.php
```

## Export
Daarna kun je de bestaande 5B pattern export gebruiken:

```bash
php tools/maintenance/phase5b/export.php
```

## Terugdraaien
Alleen automatische 5C-beslissingen resetten:

```bash
php tools/maintenance/phase5c/reset-auto.php
```

Handmatige eerdere decisions worden daarbij niet verwijderd.
