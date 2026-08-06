# LittyWatch V4.4.6 — JSON-safe batch endpoint

Opgelost:

```text
Unexpected token '<', "<br /> <b>"... is not valid JSON
```

De batchendpoint:

- schakelt HTML-weergave van PHP-waarschuwingen tijdelijk uit;
- vangt warnings/notices als exceptions af;
- buffert en verwijdert onverwachte uitvoer;
- retourneert altijd geldige JSON;
- logt de volledige technische fout server-side;
- geeft in de interface de echte foutmelding en bestandslocatie weer.

Daarnaast geeft de algemene foutafhandeling voor
`/parser-review/re-evaluate` voortaan ook JSON terug in plaats van een
HTML-foutpagina.

Start na uploaden dezelfde batch-herbeoordeling opnieuw. Wanneer er nog een
onderliggende database- of parserfout bestaat, wordt die nu leesbaar in het
voortgangsvenster getoond.
