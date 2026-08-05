# LittyWatch v0.4 — parser accuracy

Deze update richt zich op de fouten die zichtbaar waren na v0.3.

## Verbeterd

- Varianten erven nu het item uit het vorige segment:
  - `BDS q9 FC 35a | q11 Inspa 12a`
  - `Chaos Axe q9 70e, q10 30e, q11 15e`
  - vier Eternal Shield-varianten in één advertentie
- Bundels worden als één bundelaanbieding opgeslagen in plaats van de prijs willekeurig aan het laatste item te hangen.
- Verhoudingen zoals `5:1e` worden omgerekend naar een prijs per stuk.
- Stackprijzen zoals `40e/stk` krijgen hoeveelheid 250 en een correcte stukprijs.
- Hoeveelheden vóór een item zoals `250 GotT 30a` blijven behouden.
- `x6` en `[x31]` worden als voorraad gezien en verlagen niet onterecht de prijs per item.
- `7e=100k` wordt gemarkeerd als valuta-exchange en niet gebruikt voor flipkansen.
- Meer aliassen: nickgifts, ektos, Droknar's Key, Gaki, Elementalist Tomes en meer.
- Dashboard toont het exacte geparseerde segment en het aantal lage-zekerheidsregels.

## Update

Na `git pull` eenmalig uitvoeren:

1. `/install.php`
2. `/reparse.php`

De bestaande berichten blijven behouden; alleen de afgeleide aanbiedingen worden opnieuw opgebouwd.
