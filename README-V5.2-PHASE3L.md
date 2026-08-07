# LittyWatch V5.2 – Phase 3L: Price Pattern Generalization

Phase 3L generaliseert de resterende onzekere prijsnotaties uit de Data Quality Workbench.

## Nieuw

- Item-specifieke `each` quote-metadata voor o.a. Zaishen Key, Gold/Silver Zaishen Coin, Ecto, Black Dye en Star of Transference.
- Item-specifieke `stack` quote-metadata voor o.a. Cupcake, Golden Egg, War Supplies, Lunar Fortune, Candy Apple, Lockpick, Gift of the Traveler en meerdere consumables/materials.
- Kale prijzen volgen alleen expliciete itemmetadata; er is geen categoriebrede consumable/currency-aanname.
- Nieuwe quantity-total patronen:
  - `3/1e`
  - `5/11e`
  - `7/100k`
  - `5=40plat`
- `plat/platinum` wordt in quantity-total notatie als platinum (`k`) geïnterpreteerd.
- Eén prijs bij een herkenbare multi-item/shared list blijft `uncertain`.
- Conset blijft bewust conservatief omdat echte advertenties zowel per set als grotere hoeveelheden kunnen bedoelen.

## Voorbeelden

- `Cupcakes 8e` -> 8e per stack -> 0.032e per stuk.
- `War Supplies 9e` -> 0.036e per stuk.
- `Lockpicks 20e` -> 0.08e per stuk.
- `Star of Transference 1e-ea` -> 1e per stuk.
- `Black Dye 3/1e` -> 0.333e per stuk.
- `gott 5/11E` -> 2.2e per gift.
- `black dye 5=40plat` -> 0.533e per dye.
- `Cupcakes / Eggs / Honeycombs 8e` -> onzekere shared price, niet in marktstatistieken.
