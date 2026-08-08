# Phase 3T — Catalog-First Market Semantics

De Knowledge Base/catalogus is nu de enige bron voor spelersnamen.

Flow:
1. Parser leest Kamadan tekst.
2. CatalogFirstResolver zet parserbegrippen om naar concrete catalogusitems.
3. Eén generiek bericht mag meerdere concrete offers opleveren.
4. StrictCatalogGate controleert elk resultaat opnieuw.
5. Alleen canonieke catalogusnamen kunnen accepted/active worden.

Miniatures:
- kale shorthand zoals `Ghostly Hero` mag alleen naar `Miniature Ghostly Hero`
  resolven wanneer dat concrete item in de catalogus bestaat.
- `ded` of `unded` is verplicht voor publicatie.
- zonder dedication state -> Parser Review.

Tomes:
- `Elite Tome` en `Tome` zijn nooit zelfstandige marktitems.
- profession evidence in het bericht splitst naar concrete catalogusitems:
  `5 mes, 3 monk elite tomes` -> Elite Mesmer Tome x5 + Elite Monk Tome x3.
- onbekende/generieke tome zonder profession -> Parser Review.

De oorspronkelijke chatregel blijft bewaard; alleen de spelersmarkt is strict.
