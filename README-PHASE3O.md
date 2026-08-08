# Phase 3O — GWCA identifiers

Onderzoek naar GWCA/GWToolbox laat zien waarom de eerdere iconmappers fout waren:

`GW::Item` bevat afzonderlijk:
- `model_file_id`
- `model_id`
- de itemnaam

Phase 3O bewaart deze identifiers daarom afzonderlijk.

De meegeleverde publieke GWCA constants vullen bekende `model_id` waarden in. Deze worden NIET als icon-ID behandeld.

Daarnaast accepteert Inventory Icons een JSON runtime-export:
`[{"name":"Voltaic Spear","model_id":2071,"model_file_id":12345}]`

Alleen wanneer `model_file_id` overeenkomt met een lokaal `itemIcon_12345.png` wordt automatisch een 100%-koppeling gemaakt.
