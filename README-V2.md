# LittyWatch V2 foundation

Deze versie bouwt een nieuwe V2-shell **naast** de bestaande website. Er worden geen oude routes of bestanden verwijderd.

## Installatie

1. Kopieer de inhoud naar de repository-root.
2. Commit en push.
3. Pull op de VPS.
4. Open `/v2-install.php` eenmalig.
5. Open `/v2.php`.

## Waarom apart?

De huidige site bevat meerdere generaties parser- en marktcode. De V2-shell maakt het mogelijk om een nieuwe interface en nieuwe modules gecontroleerd op te bouwen, terwijl de bestaande collector en marktdata blijven werken.

## Nieuwe tabellen

- `watchlist`
- `market_snapshots`
- `alert_rules`
- `v2_settings`

Deze tabellen zijn alleen fundering; de functies worden in volgende releases geactiveerd.

## Commit

`feat(v2.0): add isolated v2 application shell and market intelligence foundation`
