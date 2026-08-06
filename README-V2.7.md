# LittyWatch V2.7 — Item Encyclopedia

## Nieuwe onderdelen

- `/v2-encyclopedia-install.php`
- `/v2-items.php`
- `/v2-item.php?key=ITEM_KEY`
- `/api/v2-items.php`
- `ItemEncyclopediaService`

## Functies

- itemcatalogus uit de bestaande structured offers;
- aantallen offers, markten en traders;
- aparte itempagina;
- marktvarianten per item;
- handmatige Wiki-sync per item;
- korte Wiki-beschrijving;
- originele Wiki-afbeelding lokaal cachen;
- bronvermelding naar Guild Wars Wiki.

## Installatie

Open één keer:

`/v2-encyclopedia-install.php`

Daarna:

`/v2-items.php`

## Rechten

De webserver moet in deze map kunnen schrijven:

`assets/items`

Bijvoorbeeld:

```bash
sudo chown -R www-data:www-data assets/items
sudo chmod 775 assets/items
```

## Opmerking

De Wiki-sync werkt bewust per item. Een latere versie kan een wachtrij en batchsync toevoegen, zodat de Wiki niet onnodig zwaar wordt belast.
