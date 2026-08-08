# Phase 3Z.2 - Upgrade List Compatibility Hotfix

Restores the proven Phase 3X `Weapon Mods` list case (for example `Zealous, Vamp Bow`) without reopening broad raw-segment splitting.

Safety rules:
- raw context is used only for `Weapon Mods` / `Mods` umbrellas;
- the raw text must contain both upgrade semantics and a weapon family;
- every candidate must have exact/alias/miniature evidence or resolve uniquely through ControlledCatalogResolver;
- the 3Z.1 transactional fallback remains intact.
