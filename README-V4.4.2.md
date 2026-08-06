# LittyWatch V4.4.2 — Isolated Review Split View

De Parser Review gebruikt nu volledig geïsoleerde CSS-classnames.

Waarom:
oude globale theme-regels konden de grid-layout overschrijven, waardoor
het detailpaneel alsnog onder de volledige wachtrij verscheen.

Desktop:
- wachtrij links;
- geselecteerd bericht rechts;
- beide panelen hebben hun eigen scrollbar;
- detail blijft sticky zichtbaar.

Alleen onder 720px wordt de pagina één kolom.
