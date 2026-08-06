# LittyWatch v1.8 — Market Intelligence Foundation

## Nieuw
- Genormaliseerde market keys voor requirements, attributes, OS/insc en relevante itemprofieleigenschappen.
- Actieve offer lifecycle: `active`, `superseded`, `expired`, `rejected`.
- Per speler/markt/type telt alleen de nieuwste advertentie als actief.
- Veilige expiry van 14 dagen; ongeldige historische jaartallen worden niet automatisch verlopen verklaard.
- Marktkwaliteitsscore voor datakwaliteit, liquiditeit en prijszekerheid.
- Marktindex met lifecycle-tellers en grootste actuele spreads.
- Marktanalyses gebruiken alleen actieve, geaccepteerde en unieke advertenties.

## Na deployment
1. Open `/install.php`.
2. Open `/reparse-v2.php` om normalized market keys opnieuw op te bouwen.
3. Open `/market-maintenance.php` om lifecycle-statussen opnieuw te berekenen.
4. Bekijk `/markets`.
