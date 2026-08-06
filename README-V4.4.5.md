# LittyWatch V4.4.5 — Batch Dependency Fix

Opgeloste fout:

```text
ParserKnowledgeRepository::__construct():
Argument #1 ($pdo) must be of type PDO,
LittyWatch\Knowledge\KnowledgeBase given
```

Oorzaak:
`ParserEngine` gaf het KnowledgeBase-object door aan een repository die een
PDO-databaseverbinding verwacht.

Oplossing:

- `Catalog` bewaart en exposeert nu zijn PDO-verbinding;
- `ParserEngine` geeft deze PDO correct door;
- batch-herbeoordeling gebruikt dezelfde dynamische aliases, uitsluitingen en
  setgroottes als de gewone parser;
- de batchknop kan daardoor de openstaande berichten opnieuw verwerken.

Na uploaden kan dezelfde knop opnieuw worden gestart. Er zijn nog geen
berichten verwerkt door de mislukte run, omdat de fout optrad vóór de eerste
batch.
