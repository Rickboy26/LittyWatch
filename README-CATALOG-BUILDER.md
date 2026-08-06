# LittyWatch GW1 Catalog Builder

Deze builder draait via **GitHub Actions**, dus niet vanaf de LittyWatch-server.
Daarmee omzeil je de huidige PHP-bestandsrechten en gebruik je niet het
geblokkeerde hosting-IP voor duizenden Wiki-verzoeken.

## Installeren

Kopieer deze bestanden naar je LittyWatch-repository:

- `.github/workflows/build-gw1-catalog.yml`
- `scripts/build_gw1_catalog.py`
- `scripts/requirements-catalog.txt`

Commit en push ze naar GitHub.

## Starten

1. Open je LittyWatch-repository op GitHub.
2. Open **Actions**.
3. Kies **Build GW1 item catalog**.
4. Klik **Run workflow**.
5. Laat diepte eerst op `2` staan.
6. Na afloop verschijnt onderaan het artifact `gw1-item-catalog`.

Het artifact bevat:

- `items.json`
- `items.csv`
- `icons/`
- `report.json`

## Belangrijk

Dit gebruikt nog steeds de openbare Guild Wars Wiki als bron, maar:
- het draait buiten jouw hosting;
- het gebruikt een nette herkenbare User-Agent;
- het wacht tussen verzoeken;
- het probeert tijdelijke fouten opnieuw;
- de uitvoer wordt éénmalig lokaal opgeslagen.

De Wiki is geen officiële game-API. Een item kan ontbreken wanneer de pagina
geen herkenbare item-infobox gebruikt. Bekijk daarom altijd `report.json`.

## Standaardcategorieën

- Miniatures
- Crafting materials
- Keys
- Rare items
- Containers
- Consumables
- Trophies
- Weapons

Deze selectie richt zich op marktwaardige items en voorkomt dat allerlei
algemene encyclopediepagina's als item worden opgeslagen.
