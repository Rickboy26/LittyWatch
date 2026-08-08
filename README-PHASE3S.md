# Phase 3S — Strict Catalog Gate

Nieuwe marktregel: een parser-resultaat mag alleen als `accepted/active` naar de
spelersmarkt wanneer het exact naar één actief concreet Knowledge Base-item
resolveert.

Geblokkeerd:
- generieke labels zoals `Miniature`, `Weapon`, `Upgrade`
- placeholders/templates met `REPLACE`
- generieke catalogusregels zoals `Any Rare FlatBow`
- onbekende fallback-namen die niet in de catalogus bestaan
- ambigue aliases die naar meerdere items wijzen

Toegestaan:
- exact catalogusitem
- exact unieke alias -> canonieke itemnaam

Admin bevat een eenmalige cleanup voor bestaande actieve offers. Niet-valide
oude offers worden naar review/rejected verplaatst; ze worden niet uit de
database verwijderd.
