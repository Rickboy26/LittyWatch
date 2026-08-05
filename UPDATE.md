# LittyWatch v0.2 update

Commit message:

```text
feat(v0.2): add resilient Kamadan collector and trading dashboard
```

## Via je pc naar GitHub

1. Download en pak de zip uit.
2. Open je lokale clone van `LittyWatch`.
3. Kopieer de inhoud van deze update over je lokale repository heen.
4. Voer uit:

```bash
git add .
git commit -m "feat(v0.2): add resilient Kamadan collector and trading dashboard"
git push origin main
```

## Daarna op de VPS

```bash
cd /var/www/hollandseglory.nl/public_html
git pull origin main
sudo chown -R www-data:www-data data logs
sudo chmod 775 data logs
```

Open vervolgens:

- `/health.php`
- `/install.php`
- `/collect.php`
- `/index.php`

De bestaande SQLite-database in `data/market.sqlite` wordt door Git genegeerd en blijft op de VPS behouden.
