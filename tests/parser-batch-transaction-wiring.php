<?php
declare(strict_types=1);

$source = (string)file_get_contents(
    dirname(__DIR__) . '/app/Services/ParserBatchReviewService.php'
);

$failed = [];

if (!str_contains(
    $source,
    "new StructuredOfferWriter(\n            \$this->pdo,\n            parserV2(),\n            new VariantNormalizer(),\n            null"
)) {
    $failed[] = 'Writer gebruikt nog een lifecycle binnen de berichttransactie.';
}

if (!str_contains($source, '$lifecycle->rebuild();')) {
    $failed[] = 'Lifecycle wordt niet na de batch herbouwd.';
}

if (str_contains(
    $source,
    "new StructuredOfferWriter(\n            \$this->pdo,\n            parserV2(),\n            new VariantNormalizer(),\n            new OfferLifecycleService"
)) {
    $failed[] = 'Geneste transactieconstructie bestaat nog.';
}

echo json_encode([
    'ok' => $failed === [],
    'failed' => $failed,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failed === [] ? 0 : 1);
