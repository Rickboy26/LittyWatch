# LittyWatch V4.4 — Review Workbench & Item Knowledge

## Parser Review

De Review Queue is opnieuw ontworpen als master-detail interface:

- links een compacte wachtrij;
- rechts het geselecteerde bericht;
- snelle goedkeuring;
- correctievelden pas zichtbaar wanneer nodig;
- aparte tabs voor aliases, uitsluitingen, setgroottes, itemkennis en correcties.

## Guild Wars Wiki-lookup

De knop **Wiki zoeken** gebruikt de MediaWiki Action API vanuit de browser met
`origin=*`. Daardoor loopt de zoekopdracht niet via het eerder geblokkeerde
hosting-IP.

Als de Wiki alsnog een 403 geeft, blijft de knop **Open Wiki** beschikbaar en
kunnen de gegevens handmatig worden opgeslagen.

## Lokale itemkennis

Nieuwe tabel:

```text
item_knowledge
```

Opgeslagen velden:

- rarity;
- itemtype;
- unique/green;
- vaste of variabele stats;
- wel of niet modificeerbaar;
- Wiki-titel, URL en samenvatting;
- vaste eigenschappen.

De itemdetailpagina toont deze kennis direct.

`Madruk's Prophecy` wordt als eerste seed toegevoegd als unique/green weapon
met vaste stats en niet-modificeerbare upgrades.
