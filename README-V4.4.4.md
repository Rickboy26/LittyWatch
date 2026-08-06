# LittyWatch V4.4.4 — Batch Herbeoordeling

Nieuwe knop op `/parser-review`:

`Herbeoordeel openstaande berichten`

De browser verwerkt openstaande reviewberichten in batches van 150. Hierdoor
loopt een herbeoordeling van circa 1800 berichten niet tegen één lange
PHP-timeout aan.

Per bericht:

- nieuwste aliases en setgroottes toepassen;
- meerdere aanbiedingen opnieuw splitsen;
- WTT en barter opnieuw verwerken;
- services uitsluiten;
- character-name sales uitsluiten;
- guildreclame en guildrecruitment uitsluiten;
- legacy en structured offers opnieuw opbouwen;
- confidence en reviewstatus opnieuw bepalen;
- oude, verweesde reviewregels opruimen.

Tijdens de run toont LittyWatch live:

- gecontroleerd;
- herkend;
- uitgesloten;
- nog review;
- mislukt.

Na afloop wordt de Review Queue automatisch vernieuwd.
