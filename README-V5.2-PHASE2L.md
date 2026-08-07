# LittyWatch V5.2 — Phase 2L Specificity & Alias Guard Cleanup

Targets the Phase 2K residual queue (6 no_catalog_item / 75 low_confidence).

- Adds concrete catalog identities for Shinobi Blade, Plagueborn Daggers, Chromium Shards, Plagueborn Staff and Aptitude Not Attitude.
- Normalizes truncated Strongroot's Shelter and Plagueborn/Chromium spellings seen in review.
- Prevents bare `cons` from becoming Conset in Tengu Guard / GoM / EoC / AoS barter text.
- Prevents bare `guard` from becoming Imperial Guard Reinforcement Order.
- Drops dedication-only and rarity/category-only grammar fragments such as `Ded` and `new FoW Green`.
- Lets strong exact named catalog identities clear low-confidence when no price is present.
