LittyWatch - Armbrace of Truth manual image override

Dit pakket bevat:
- assets/game-items/manual/armbrace-of-truth.png
  Het correcte 64x64 Armbrace of Truth inventory icon dat je hebt aangeleverd.
- tools/maintenance/install-armbrace-manual-override.php
  Maakt eerst een backup van item-image.php en voegt daarna een hoogste-prioriteit
  override toe die ALLEEN Armbrace of Truth onderschept.
- tools/maintenance/remove-armbrace-manual-override.php
  Verwijdert alleen het toegevoegde override-blok.

INSTALLATIE
1. Pak deze ZIP uit in de root van LittyWatch:
   /var/www/hollandseglory.nl/public_html

2. Draai:
   php -l tools/maintenance/install-armbrace-manual-override.php
   php tools/maintenance/install-armbrace-manual-override.php
   php -l item-image.php

3. Test:
   https://hollandseglory.nl/item-image.php?item=Armbrace%20of%20Truth&size=72&v=manual1

De response krijgt de header:
   X-LittyWatch-Image-Source: manual-override

De installer maakt automatisch een backup onder:
   storage/backups/

ROLLBACK
   php tools/maintenance/remove-armbrace-manual-override.php

Opmerking:
Deze patch raakt alleen de Armbrace-afbeelding. De bredere inscription/icon-linking en
miniature canonicalisatie kunnen daarna als aparte structurele patch worden aangepakt.
