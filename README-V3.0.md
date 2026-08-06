# LittyWatch V3.0 — Local Game Asset Catalog

V3.0 importeert originele Guild Wars-inventoryiconen uit het pakket dat door
`LittyWatch Asset Extractor v0.3` wordt gemaakt.

## Nieuw

- `v3-assets.php`: upload/import, overzicht en handmatige koppeling.
- `app/V2/Assets/AssetCatalogService.php`: veilige ZIP-import en opslag.
- Tabellen `asset_imports` en `item_assets` worden automatisch aangemaakt.
- Iconen worden lokaal opgeslagen onder `assets/game-items/<batch>/`.
- Gekoppelde iconen verschijnen direct in `v2-items.php` en `v2-item.php`.
- Interne bestands-ID uit namen zoals `itemIcon_104296.png` wordt bewaard als
  `dat_file_id`; dit wordt bewust niet als Guild Wars-model-ID voorgesteld.

## Importeren

Open:

`https://hollandseglory.nl/v3-assets.php`

Upload daar de door de extractor gemaakte ZIP.

Wanneer PHP de ZIP door `upload_max_filesize` of `post_max_size` weigert:

1. upload de ZIP via FTP/SFTP naar `imports/assets/`;
2. open `v3-assets.php`;
3. kies het pakket onder **ZIP vanaf server importeren**.

## Koppelen

Het huidige extractor-pakket bevat 5.277 iconen, maar nog geen itemnamen.
Daarom worden de iconen eerst als ongekoppeld geïmporteerd. Zoek of blader door
de iconen en vul bij het juiste plaatje de `item_key` van een bestaand
marktitem in. De datalist toont bestaande marktitems.

Fase 2 van de Windows-extractor kan later namen/model-ID's toevoegen. Dezelfde
importer koppelt die dan automatisch wanneer de naam exact overeenkomt.

## Serververeisten

- PHP 8.1+
- PDO SQLite
- ZipArchive / PHP zip-extensie
- schrijfrechten op `assets/game-items/`
- voor serverimport: schrijfrechten op `imports/assets/`
