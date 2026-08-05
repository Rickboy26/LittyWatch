# LittyWatch v1.1 — parser integrity hotfix

## Opgelost

- Korte alias `got` is verwijderd; `you got` en `Forgotten` kunnen niet meer als Gift of the Traveler worden gezien.
- Alle legacy itemaliases vereisen nu echte woordgrenzen.
- De te algemene Voltaic Spear-alias `VS` is verwijderd.
- Itempagina’s tonen standaard alleen geaccepteerde aanbiedingen.
- Na installatie moet `reparse.php` één keer worden uitgevoerd om bestaande foutieve records opnieuw op te bouwen.

## Update

```bash
git add .
git commit -m "fix(v1.1): enforce alias boundaries and clean item pages"
git push origin main
```

Op de server:

```bash
cd /var/www/hollandseglory.nl/public_html
git pull origin main
```

Open daarna één keer `/reparse.php`.
