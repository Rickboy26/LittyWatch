# LittyWatch V5.2 — Phase 3H: Commodity & Stack Semantics

Phase 3H centralizes item-specific market quotation semantics in the catalog.

- New catalog fields: `market_quote_basis`, `market_quote_size`, `market_display_basis`.
- Legacy Phase 3G `market_price_basis` / `market_stack_size` remain readable for compatibility.
- Bare prices for explicitly stack-quoted items are normalized using their declared quote size.
- Explicit `stk`, `stack`, `ea/stack`, and multi-stack totals converge on the same canonical item quantity.
- Royal Gift and Candy Corn are declared as 250-item stack-quoted markets.
- No category-wide consumable inference: items such as Conset stay conservative unless explicitly declared.
- Parser version bumped to `v2.4-phase3h` so a full reparse can distinguish refreshed offers.
