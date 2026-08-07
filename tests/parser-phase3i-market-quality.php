<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'service' => is_file($root.'/app/Market/MarketQualityService.php'),
    'schema_status' => str_contains((string)file_get_contents($root.'/bootstrap.php'), "price_quality_status"),
    'market_gate' => str_contains((string)file_get_contents($root.'/app/Repositories/MarketRepository.php'), "price_quality_status,'trusted')='trusted'"),
    'review_seed' => str_contains((string)file_get_contents($root.'/app/Repositories/ParserReviewRepository.php'), "IN ('uncertain','outlier')"),
    'reparse_quality' => str_contains((string)file_get_contents($root.'/tools/maintenance/reparse-all.php'), 'MarketQualityService'),
    'quality_cards' => str_contains((string)file_get_contents($root.'/app/Views/items/show.php'), 'bruikbare prijzen'),
];
$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($failed !== []) {
    fwrite(STDERR, "Phase 3I failed: ".implode(', ', $failed)."\n");
    exit(1);
}
echo "Phase 3I market-quality wiring OK\n";
