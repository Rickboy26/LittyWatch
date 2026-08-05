# LittyWatch v0.3 — parserfase

Deze update voegt een aparte `offers`-tabel toe. Eén Kamadan-chatbericht kan daardoor meerdere items en prijzen opleveren.

## Na `git pull`

Open één keer:

1. `/install.php`
2. `/reparse.php`
3. `/`

`reparse.php` verwerkt alle reeds opgeslagen berichten opnieuw. Verwijder of beveilig dit bestand later wanneer de parser stabiel is.

Commitbericht:

`feat(parser): add multi-offer parsing and canonical item recognition`
