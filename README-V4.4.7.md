# LittyWatch V4.4.7 — Batch Transaction Fix

## Oorzaak van 1696 mislukkingen

De batch begon voor ieder bericht een PDO-transactie. Daarna riep
`StructuredOfferWriter` de `OfferLifecycleService` aan. Die service probeerde
binnen dezelfde PDO-verbinding opnieuw `beginTransaction()` uit te voeren.

SQLite/PDO ondersteunt daar geen geneste transacties. Daardoor faalden alle
normale marktberichten met een fout zoals:

```text
There is already an active transaction
```

Enkele service/noise/guildberichten konden wel worden uitgesloten, omdat die
de structured writer niet bereikten. Dat verklaart waarom de eerdere run
slechts ongeveer twee resultaten vond.

## Oplossing

- structured offers worden nog steeds atomair per bericht opgebouwd;
- de lifecycle service draait niet meer binnen de berichttransactie;
- lifecycle wordt één keer na iedere batch herbouwd;
- maximaal vijf concrete foutvoorbeelden worden in het voortgangsvenster
  getoond wanneer er nog iets misgaat.

De mislukte transacties zijn teruggedraaid en hebben de bestaande gegevens
niet half aangepast. Start de batch na uploaden opnieuw vanaf 0%.
