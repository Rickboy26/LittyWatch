<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = file_get_contents($root . '/app/Repositories/MarketRepository.php');
$maintenance = file_get_contents($root . '/app/Controllers/MaintenanceController.php');
$items = json_decode((string)file_get_contents($root . '/app/Data/items.json'), true);

$fail = static function(string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

if (!str_contains($repo, 'FROM structured_offers o')) $fail('Items repository is not backed by structured_offers.');
if (!str_contains($repo, "price_currency,'') IN ('a','e','k')")) $fail('Trusted price filter is missing explicit currency requirement.');
if (!str_contains($repo, "NOT IN ('bundle','currency_exchange','unknown')")) $fail('Ambiguous price bases are not excluded from statistics.');
if (!str_contains($maintenance, 'new ParserEngine(new Catalog(')) $fail('Full rebuild does not instantiate a fresh deployed parser.');

if (!str_contains($repo, 'GROUP BY lower(o.item)')) $fail('Item directory does not collapse casing duplicates.');
if (!str_contains($repo, "o.item NOT LIKE 'Bundle:%'")) $fail('Bundle pseudo-items are not excluded from the Items directory.');

echo "Phase 3A/3B market integrity wiring OK\n";
