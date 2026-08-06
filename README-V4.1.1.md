# LittyWatch V4.1.1 — Itemruil en timestamps

## WTT / item-voor-item

Ruiladvertenties worden niet meer als prijsloze advertenties behandeld.

Voorbeeld:

```text
WTT Tengu flare 1:1 War supplies
```

wordt:

```text
Type: trade
Aangeboden: 1 Tengu Support Flare
Gevraagd: 1 War Supplies
Prijsbasis: barter
```

Ook verhoudingen zoals `2:1`, `1=1` en `1 for 2` worden ondersteund.

De database bewaart hiervoor:

- `exchange_item`
- `exchange_item_key`
- `exchange_give_quantity`
- `exchange_receive_quantity`

Er wordt bewust geen ectoprijs berekend, omdat een ruilverhouding geen
geldprijs is.

## Datums

Unix-timestamps in milliseconden worden nu eerst naar seconden omgerekend.
Hierdoor ontstaan geen jaartallen zoals `58567` meer.

## Bestaande gegevens herstellen

Open na uploaden:

```text
Beheer → Parser opnieuw uitvoeren
```

Dit:

1. herbouwt `offers`;
2. herbouwt `structured_offers`;
3. verwerkt WTT opnieuw;
4. repareert numerieke milliseconde-timestamps die nog in de database staan.

## Tests

```bash
php tests/parser-stack-notation.php
php tests/parser-barter.php
```
