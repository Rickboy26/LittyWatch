# LittyWatch V2.8.3 — Market detail type hotfix

## Opgelost

- De centrale HTML escape-helper `h()` accepteert nu strings, integers, floats,
  booleans, null en `Stringable` objecten.
- Markt-detailpagina's crashen daardoor niet meer wanneer SQLite of een
  formatter een numerieke waarde als `float` teruggeeft.
- Ongeldige samengestelde waarden worden veilig als lege tekst weergegeven.
- `ENT_SUBSTITUTE` voorkomt dat een ongeldig UTF-8 teken de volledige pagina
  laat mislukken.

## Betroffen fout

`h(): Argument #1 ($value) must be of type ?string, float given`
