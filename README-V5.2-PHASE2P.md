# LittyWatch V5.2 — Phase 2P Final Low-Confidence Context Cleanup

Targets the 14 low-confidence rows left after Phase 2O.

- Strict whitelist for Gift of the Traveler aliases; polluted learned `got` can no longer match prose like `show me what you got`.
- Expands `Spear Def/Ench/Cruel/Shock` into the four actual spear upgrade components.
- Adds Cruel/Shocking Spearhead and Spear Grip of Defense/Enchanting to the static catalog.
- Repairs the exact observed `large equipment staff` typo to Large Equipment Pack.
- Suppresses learned modifier words as standalone low-confidence items in explicit mod advertisements.
- Fixes the Phase 2O generic-family regex warning.
