<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use LittyWatch\Market\OfferLifecycleService;

installSchema();
$result = (new OfferLifecycleService(db()))->rebuild();

printf("Lifecycle opnieuw opgebouwd.\n");
printf("Active:     %d\n", (int)($result['active'] ?? 0));
printf("Superseded: %d\n", (int)($result['superseded'] ?? 0));
printf("Expired:    %d\n", (int)($result['expired'] ?? 0));
