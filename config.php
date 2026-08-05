<?php
declare(strict_types=1);
return [
    'app_name' => 'GW1 Market Scanner',
    'timezone' => 'Europe/Amsterdam',
    'db_path' => __DIR__ . '/data/market.sqlite',
    'kamadan_endpoint' => 'https://kamadan.gwtoolbox.com/m',
    'fallback_endpoint' => 'https://kamadan.decltype.org/',
    'request_timeout' => 20,
    'max_messages_per_run' => 250,
];
