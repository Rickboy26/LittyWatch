# LittyWatch V5.2 — Phase 3D

Price semantics & market quality:
- `27e x6` is treated as 6 units at 27e each, never 27e total divided by 6;
- explicit `/ea`, `each`, `per unit` and `price x quantity` notation is classified as unit pricing;
- explicit `N items for Xe` remains a total price and may be divided by quantity;
- currency conversions such as `1750e = 64a` cannot override an earlier explicit unit price;
- prices from another item slice no longer leak into an unpriced item (`ARMBRACES | BDS 17a`);
- ambiguous Armbrace of Truth prices such as `Arms 250e` or bare armbrace-denominated prices remain visible but are excluded from trusted unit-price statistics;
- exact bare prices for concrete non-commodity items such as `BDS 30a` remain valid inferred unit prices;
- Armbrace of Truth is always displayed primarily in ecto, avoiding circular armbrace-in-armbrace presentation;
- highest WTB / lowest WTS and market statistics use only trusted price observations.
