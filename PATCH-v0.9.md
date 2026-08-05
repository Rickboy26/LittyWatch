# LittyWatch v0.9 — live dashboard API

## Nieuw

- JSON-endpoint op `/api/dashboard`.
- Automatisch vernieuwen van tellers, aanbiedingen en flip-kansen iedere 30 seconden.
- Pauze- en handmatige vernieuwknop.
- Extra filter op `accepted` / `review`.
- Instelbaar aantal resultaten: 50, 100 of 200.
- Nieuwste berichttijd zichtbaar op het dashboard.
- Apache rewrite-regels voor applicatieroutes, terwijl bestaande PHP-tools bereikbaar blijven.

## Update

Er is geen database-migratie nodig.

```bash
git add .
git commit -m "feat(v0.9): add live dashboard API and automatic refresh"
git push origin main
```

Op de VPS:

```bash
cd /var/www/hollandseglory.nl/public_html
git pull origin main
```

Test:

- `/`
- `/api/dashboard`

Als `/api/dashboard` een 404 geeft, controleer dan of Apache `mod_rewrite` en `AllowOverride All` voor deze webroot toestaat.
