# LittyWatch V5.0 — Structured Market Parser

V5 vervangt losse fallback-regels door een expliciete verwerkingsvolgorde:

```text
MarketMessageGate
→ Trade block splitter
→ Grammar segmenter
→ Semantic aliases
→ Catalog/generic family recognizer
→ Requirement/attribute extractor
→ Upgrade and inscription extractor
→ Price parser
→ Confidence and review status
→ Deduplication
```

## Vroeg uitgesloten

- guild recruitment en cape-trimadvertenties;
- missions/rush/services;
- price checks (`PC on ...`);
- greetings en niet-specifieke verzoeken;
- contact/noisetekst.

Hierdoor kunnen woorden zoals `Red`, `Blue` en `Purple` uit een guildservice
niet meer als item in de markt belanden.

## Gestructureerde eigenschappen

- q/r/rq/req requirements;
- attribute-only requirements, bijvoorbeeld `req channeling`;
- inscribable en oldschool;
- dedicated/undedicated;
- unidentified;
- weapon upgrades en inscriptions zoals zealous, enchanting, +30 HP,
  15^50, 20/20, Forget Me Not en Aptitude Not Attitude.

## Generieke families

Wanneer een specifieke skin nog niet in de catalogus staat, kan V5 geldige
families herkennen zoals Flatbow, Shield, Staff, Wand, Focus, Sword, Axe,
Hammer, Spear, Scythe en Daggers.

## Nieuwe cataloguskennis

V5 bevat een eerste uitbreiding met veel voorkomende resterende reviewitems
en weapon upgrades. De Review Queue toont daarnaast een overzicht van de
resterende `quality_reason`-groepen, zodat volgende verbeteringen meetbaar
blijven.

## Na installatie

1. Upload de volledige ZIP over V4.4.8.
2. Open `/parser-review`.
3. Klik `Herbeoordeel openstaande berichten`.
4. Controleer de nieuwe verdeling en de V5-diagnostiek.
