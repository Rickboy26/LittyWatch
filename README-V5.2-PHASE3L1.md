# LittyWatch V5.2 – Phase 3L.1: Conset & Equals Semantics Hotfix

## Fixes
- `Conset 2e` = 2e per conset.
- `Conset 9a` = 9a per stack (250).
- Explicit `Conset 2e/ea` and `Conset 9a/stk` keep their explicit basis.
- `=` is no longer blindly treated as currency conversion.
  - `Emerald Blade = 15a` -> 15a per item.
  - `Asterius Scythe = 8e` -> 8e per item.
  - `Memory 20%=1e` -> 1e per upgrade.
  - `Soulbreaker r13=4a` -> 4a per item.
- True money conversions such as `1750e = 64a` remain conversion syntax and cannot override an earlier explicit unit price.
