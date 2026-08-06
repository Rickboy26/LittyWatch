# LittyWatch V2.2 — Market Intelligence Core

Deze update bouwt een aparte, herberekenbare marktlaag bovenop `structured_offers`.

## Nieuw

- `market_intelligence` tabel met één regel per genormaliseerde marktvariant;
- beste WTB, beste WTS en medianen per stuk;
- ecto- én armbraceweergave;
- liquidity score;
- demand/supply score;
- price-confidence score;
- deal score voor mogelijke spreads;
- nieuwe pagina `/v2-intelligence.php`;
- handmatige refresh en CLI/cron refresh.

## Installatie

1. Upload/commit alle bestanden.
2. Open `/v2-intelligence-install.php`.
3. Open `/v2-intelligence-refresh.php`.
4. Open `/v2-intelligence.php`.

## Optionele cronjob

```cron
*/15 * * * * /usr/bin/php /var/www/hollandseglory.nl/public_html/cron/v2-intelligence.php >> /var/www/hollandseglory.nl/public_html/logs/v2-intelligence.log 2>&1
```

## Belangrijk

De score is heuristisch. Het systeem noemt iets alleen een mogelijke spread; een Kamadan-advertentie is geen bewezen transactie.
