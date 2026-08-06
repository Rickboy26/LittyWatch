# GW1 Market Scanner – testversie

## Eisen
- PHP 8.0+
- PDO SQLite (`php-sqlite3`)
- DOM (`php-xml`)
- cURL aanbevolen (`php-curl`)

## Installatie
1. Upload de volledige map naar je webserver.
2. Maak `data/` schrijfbaar voor de webserver, bijvoorbeeld `chmod 775 data`.
3. Open `health.php`.
4. Open `install.php`.
5. Open `collect.php` om handelsberichten op te halen.
6. Open `index.php`.

Er is geen MySQL-database nodig; de app gebruikt automatisch `data/market.sqlite`.

## Cronjob
```cron
* * * * * /usr/bin/php /pad/naar/gwscanner/cron/collect.php >> /pad/naar/gwscanner/logs/collector.log 2>&1
```

## Opmerking
De collector probeert eerst het Kamadan GWToolbox JSON-endpoint en gebruikt bij problemen de publieke Decltype-feed als fallback. De parser is een MVP en ondersteunt een kleine aliaslijst.

## v1.5 Structured Offers
Open `/reparse-v2.php` en daarna `/structured-offers`. De legacy offers blijven onaangetast.
