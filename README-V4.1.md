# LittyWatch V4.1 — Parser & Item Detail

## Parserfix

Handelsnotatie wordt niet langer onderdeel van de itemnaam:

- `Rez /stk` → item `Rez`
- `Rez 4e/stk` → item `Rez`, prijsbasis `stack`, hoeveelheid `250`
- `Rez per stack` → item `Rez`
- `Rez [x250]` → item `Rez`

De fix is toegepast op zowel:

- de offertabel die Dashboard en Items gebruiken;
- de structured-offers voor Market Intelligence.

## Bestaande foutieve records herstellen

Na uploaden:

1. Open **Beheer**.
2. Klik **Parser v2 opnieuw uitvoeren**.
3. Deze actie herbouwt nu zowel `offers` als `structured_offers`.

Daarna verdwijnen oude namen zoals `Rez /stk` uit Dashboard en Items.

## Nieuwe itemdetailpagina

De itempagina bevat nu:

- groot lokaal itemicoon;
- hoogste WTB en laagste WTS;
- medianen en spread;
- prijsgrafiek;
- aparte kolommen voor actieve kopers en verkopers;
- marktvarianten;
- datakwaliteit;
- inklapbare ruwe aanbiedingen;
- watchlistknop;
- nette item-niet-gevondenpagina.

## Test

```bash
php tests/parser-stack-notation.php
```
