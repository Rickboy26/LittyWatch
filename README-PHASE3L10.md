# LittyWatch V5.2 Phase 3L.10 — Live Outlier Semantics

Gerichte follow-up op de 16 resterende markt-outliers na 3L.9.

- Conset bare `9a` wordt als 1 stack van 250 geïnterpreteerd.
- Conset bare `2e` blijft een prijs per conset.
- Expliciete Conset stacknotatie blijft leidend.
- Mixed-basis markten (Gift of the Traveler, Tengu Support Flare) krijgen bij een kale prijs geen impliciete stack/per-item basis meer.
- Dit voorkomt dat een eerder door de parser ingevulde catalogusbasis alsnog een kale mixed-basis quote tot trusted marktprijs promoveert.
- Parser/reparse labels bijgewerkt naar Phase 3L.10.
