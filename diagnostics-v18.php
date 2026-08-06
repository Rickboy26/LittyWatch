<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$checks = [
    'OfferLifecycleService file' => __DIR__ . '/app/Market/OfferLifecycleService.php',
    'VariantNormalizer file' => __DIR__ . '/app/Market/VariantNormalizer.php',
    'StructuredOfferWriter file' => __DIR__ . '/app/Market/StructuredOfferWriter.php',
    'StructuredMarketController file' => __DIR__ . '/app/Controllers/StructuredMarketController.php',
    'StructuredMarketRepository file' => __DIR__ . '/app/Repositories/StructuredMarketRepository.php',
];

$result = [];
foreach ($checks as $label => $path) {
    $result[$label] = ['path' => $path, 'exists' => is_file($path), 'readable' => is_readable($path)];
}

$result['autoload OfferLifecycleService'] = class_exists(\LittyWatch\Market\OfferLifecycleService::class);
$result['autoload StructuredMarketController'] = class_exists(\LittyWatch\Controllers\StructuredMarketController::class);
$result['request_uri'] = $_SERVER['REQUEST_URI'] ?? null;
$result['document_root'] = $_SERVER['DOCUMENT_ROOT'] ?? null;

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
