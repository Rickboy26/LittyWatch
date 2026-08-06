<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

// Expliciete includes als veilige fallback naast de autoloader.
require_once __DIR__ . '/app/Market/VariantNormalizer.php';
require_once __DIR__ . '/app/Market/OfferLifecycleService.php';
require_once __DIR__ . '/app/Market/StructuredOfferWriter.php';

installSchema();

$lifecycle = new \LittyWatch\Market\OfferLifecycleService(db());
$writer = new \LittyWatch\Market\StructuredOfferWriter(
    db(),
    parserV2(),
    new \LittyWatch\Market\VariantNormalizer(),
    null
);

$rows = db()->query('SELECT id, message FROM messages ORDER BY id')->fetchAll();
$created = 0;

foreach ($rows as $row) {
    $created += $writer->parseMessage((int) $row['id'], (string) $row['message'], true);
}

$lifecycleResult = $lifecycle->rebuild();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'parser_version' => 'v2.2.1',
    'messages_reparsed' => count($rows),
    'structured_offers_created' => $created,
    'lifecycle' => $lifecycleResult,
    'legacy_offers_untouched' => true,
    'review_url' => '/structured-offers',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
