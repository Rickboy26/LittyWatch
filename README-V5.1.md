# LittyWatch V5.1 — Guild Wars Knowledge Pack Foundation

## Nieuwe beheerpagina

```text
/knowledge-pack
```

Deze pagina haalt Guild Wars Wiki-categorieën rechtstreeks vanuit de browser
op. De browser gebruikt de MediaWiki Action API met CORS (`origin=*`), zodat
een eerder geblokkeerd hosting-IP geen blokkade hoeft te zijn.

## Veilige importflow

```text
Wiki categorymembers
→ details, categories en redirects
→ lokale staging
→ validatie
→ publiceren naar knowledge-pack JSON
→ Catalog merge
→ parser herbeoordelen
```

Wiki-pagina’s worden niet direct blind in de productcatalogus gezet. Eerst
komen ze in `data/wiki-knowledge`. Alleen na **Staging publiceren** worden
marktwaardige pagina’s omgezet naar:

- `app/Data/knowledge-pack/items.json`
- `app/Data/knowledge-pack/aliases.json`
- `app/Data/knowledge-pack/metadata.json`

Redirects uit de Wiki worden automatisch aliases.

## Eerste profielen

- unique/green items;
- weapon upgrades;
- inscriptions;
- miniatures;
- tonics;
- consumables;
- tomes;
- weapon skins.

De categorieën staan in `app/Data/knowledge-pack/sources.json` en kunnen later
zonder parsercode worden uitgebreid of aangepast.

## Parserintegratie

`Catalog` voegt nu samen:

1. bestaande `items.json`;
2. gepubliceerd knowledge pack;
3. dynamische databasekennis.

De bestaande catalogus wordt dus niet vervangen.

## Installatie

1. Upload de volledige ZIP over V5.0.
2. Open `/knowledge-pack`.
3. Test eerst één categorie, bijvoorbeeld **Green / unique items**.
4. Controleer de staging.
5. Klik **Staging publiceren**.
6. Open Parser Review en draai **Herbeoordeel openstaande berichten**.

## Opmerking

Categorieën op een communitywiki kunnen wijzigen. De importer toont daarom
fouten per categorie en bewaart resultaten eerst in staging. De server-side
CLI-importer is optioneel; de browserimporter is de voorkeursroute.
