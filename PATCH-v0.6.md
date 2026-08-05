# LittyWatch v0.6 — Parser v2 foundation

Deze release plaatst een nieuwe modulaire parser naast de bestaande v0.5-parser. De live collector en database blijven voorlopig v0.5 gebruiken.

## Nieuw

- PSR-achtige autoloader zonder Composer.
- Normalizer, offer splitter, tokenizer, catalog, item matcher, modifier matcher, price matcher en confidence scorer als losse klassen.
- JSON-databestanden voor items, modifiers en reject-patterns.
- `parser-v2-test.php`: browserlab om losse Kamadan-berichten met v2 te testen.
- `tests/parser-v2-smoke.php`: eenvoudige CLI-smoketest.

## Testen

Open:

`https://hollandseglory.nl/parser-v2-test.php`

CLI:

```bash
php tests/parser-v2-smoke.php
```

## Belangrijk

V2 schrijft in deze fase nog niets naar SQLite. Daardoor kan de engine veilig naast de huidige productieparser worden verbeterd en vergeleken.
