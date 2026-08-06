# LittyWatch V4.0 — Refactor fase 2.1

Hotfix voor de kale Watchlist/Alerts-weergave.

De centrale LittyWatch-stylesheet wordt nu naast de normale CSS-link ook als
kritieke inline CSS in de gedeelde layout opgenomen. Daardoor blijft het thema
werken wanneer Apache, browsercache of een onvolledige upload het CSS-bestand
niet via `/assets/css/app.css` serveert.

Na uploaden: hard verversen met Ctrl+F5.
