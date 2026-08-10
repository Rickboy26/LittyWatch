<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Alleen CLI.\n");
    exit(2);
}

installSchema();
$lifecycle = new \LittyWatch\Market\OfferLifecycleService(db());
$expired = $lifecycle->expireStaleOffers();
printf("Active offer expiry klaar.\n");
printf("Expiry-grens: %d uur\n", $lifecycle->expiryHours());
printf("Nieuw expired: %d\n", $expired);
