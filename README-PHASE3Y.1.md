# Phase 3Y.1 - Mini dedication order hotfix

Fixes miniature normalization when `mini` appears before the dedication token, e.g. `mini unded Asura`.

Both `mini unded Asura` and `unded mini Asura` now resolve to `Miniature Asura` with variant `unded`, while the dedication token remains metadata rather than part of the catalogue identity.
