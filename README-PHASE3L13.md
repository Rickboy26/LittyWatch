# LittyWatch V5.2 Phase 3L.13 — Safe Bulk-Pair Recovery

Gerichte cleanup van de resterende uncertain-prijzen.

- Herkent expliciete stack + bulkpair notatie zoals `GOTT STACK -27A/ 2-53A`.
- Gebruikt de enkel-stack prijs als canonical quote en behandelt de meervoudsprijs als bulkdeal.
- Valideert dat de bulkprijs per stack logisch dicht bij de enkel-stack prijs ligt.
- Onlogische bulkpairs blijven uncertain.
- Bestaande 3L.7–3L.12 veiligheidsregels blijven intact.
