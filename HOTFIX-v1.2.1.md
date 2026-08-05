# LittyWatch v1.2.1 analytics hotfix

Fixes the item analytics SQL error caused by querying the non-existent `offers.price_type` column.
The schema uses `offers.price_basis`.

Commit message:

`fix(v1.2.1): use price_basis in item analytics query`
