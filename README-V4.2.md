# LittyWatch V4.2 — Parser Intelligence Rebuild

De parser werkt nu als pipeline:

1. normaliseren;
2. WTB/WTS/WTT bepalen;
3. slim segmenteren op `|`, `^`, `;`, combinaties en context;
4. contactregels, services en character-name sales classificeren;
5. Guild Wars-afkortingen normaliseren;
6. items, modifiers, hoeveelheden en prijzen koppelen;
7. parserstatus opslaan voor de Live Feed.

Ondersteund:

- meerdere aanbiedingen in één handelszin;
- `pm me` en `open trade` negeren;
- services en verkochte character names uitsluiten;
- `Unicorn's Wrath, 10 Soul Reaping, +5 energy` als één wapen;
- Rin Relic set = 25;
- dedicated/undedicated miniatures;
- Everlasting Tonics;
- gewone en Elite Tomes voor alle professions;
- bestaande stack- en WTT-regels uit V4.1.1.

Na uploaden: **Beheer → Parser opnieuw uitvoeren**.
