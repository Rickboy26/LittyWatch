<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

if (PHP_SAPI !== 'cli') { fwrite(STDERR,"Alleen CLI.\n"); exit(2); }

installSchema();
$r=(new \LittyWatch\Market\MarketDataRetentionService(db()))->report();

printf("=== MARKET DATA RETENTION ===\n");
printf("Messages totaal:                 %d\n", $r['messages']);
printf("Structured offers totaal:        %d\n", $r['structured_offers']);
printf("Raw message retention:           %d dagen\n", $r['retention_days']);
printf("Cutoff:                          %s\n", $r['cutoff']);
printf("Ouder dan cutoff:                %d\n", $r['older_than_retention']);
printf("Beschermd door handmatige review:%d\n", $r['protected_reviewed_messages']);
printf("Oude berichten opruimbaar:       %d\n", $r['eligible_old_messages']);
printf("Hard message plafond:            %d\n", $r['max_messages']);
printf("Boven hard plafond:              %d\n", $r['over_hard_cap']);
printf("Historie-cap per markt/type:     %d\n", $r['history_cap_per_market']);
printf("Overtollige historical offers:   %d\n", $r['historical_offer_overflow']);
printf("\nDit rapport verwijdert niets. Gebruik prune-market-data.php --apply.\n");
