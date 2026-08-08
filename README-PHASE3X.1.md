# LittyWatch Phase 3X.1 hotfix

Fixes miniature list segmentation introduced in Phase 3X:

- recognizes GW shorthand `uded` as `unded`;
- carries miniature state across list entries such as `Uded Celestial Sheep and Rat`;
- strips `ded`/`unded` metadata from the catalogue lookup identity;
- preserves the state as the offer variant instead.

Regression target:

`Uded Celestial Sheep and Rat` => `Miniature Celestial Sheep` (unded) + `Miniature Celestial Rat` (unded).
