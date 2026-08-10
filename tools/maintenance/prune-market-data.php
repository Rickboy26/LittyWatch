<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

if (PHP_SAPI !== 'cli') { fwrite(STDERR,"Alleen CLI.\n"); exit(2); }
$options=getopt('', ['apply','vacuum']);
$apply=array_key_exists('apply',$options);
$vacuum=array_key_exists('vacuum',$options);

installSchema();
$service=new \LittyWatch\Market\MarketDataRetentionService(db());
if(!$apply){
    $r=$service->report();
    printf("DRY RUN - er wordt niets verwijderd.\n");
    printf("Oude berichten opruimbaar: %d\n",$r['eligible_old_messages']);
    printf("Boven hard plafond: %d\n",$r['over_hard_cap']);
    printf("Overtollige historical offers: %d\n",$r['historical_offer_overflow']);
    printf("\nToepassen: php tools/maintenance/prune-market-data.php --apply\n");
    printf("Toepassen + SQLite ruimte teruggeven: php tools/maintenance/prune-market-data.php --apply --vacuum\n");
    exit(0);
}

$r=$service->prune($vacuum);
printf("Market-data cleanup klaar.\n");
printf("Historical offers verwijderd: %d\n",$r['historical_offers_deleted']);
printf("Oude messages verwijderd:      %d\n",$r['old_messages_deleted']);
printf("Hard-cap messages verwijderd:  %d\n",$r['hard_cap_messages_deleted']);
printf("Messages nu:                   %d\n",$r['after']['messages']);
printf("Structured offers nu:          %d\n",$r['after']['structured_offers']);
printf("VACUUM:                         %s\n",$r['vacuum']?'ja':'nee');
