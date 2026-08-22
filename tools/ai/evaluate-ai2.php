<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use LittyWatch\AI\LocalAiClient;
use LittyWatch\AI\Schema\TradeParseSchema;

$file = __DIR__ . '/../../data/ai/hard-cases.ndjson';

if (!is_file($file)) {
    fwrite(STDERR, "Dataset ontbreekt: {$file}\n");
    exit(1);
}

$limit = null;
$onlyId = null;

foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = max(1, (int)$m[1]);
    }

    if (preg_match('/^--id=(.+)$/', $arg, $m)) {
        $onlyId = trim($m[1]);
    }
}

$rows = [];
$fh = fopen($file, 'rb');

if ($fh === false) {
    throw new RuntimeException("Kan dataset niet openen.");
}

while (($line = fgets($fh)) !== false) {
    $line = trim($line);

    if ($line === '') {
        continue;
    }

    $candidate = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

    if (
        $onlyId !== null
        && (string)($candidate['id'] ?? '') !== $onlyId
    ) {
        continue;
    }

    $rows[] = $candidate;

    if ($limit !== null && count($rows) >= $limit) {
        break;
    }
}

fclose($fh);

$client = new LocalAiClient();

$fields = [
    'trade_type',
    'item_text',
    'requirement',
    'attribute_token',
    'price_amount',
    'price_currency',
    'oldschool',
    'inscribable',
    'mods',
    'gold_value',
];

$fieldScores = [];

foreach ($fields as $field) {
    $fieldScores[$field] = [
        'correct' => 0,
        'total' => 0,
    ];
}

$messages = 0;
$validJson = 0;
$exactMessages = 0;
$expectedOffers = 0;
$actualOffers = 0;
$totalDuration = 0.0;

$errorTypes = [
    'invalid_json' => 0,
    'offer_count' => 0,
    'item_identity' => 0,
    'requirement' => 0,
    'attribute' => 0,
    'trade_type' => 0,
    'price' => 0,
    'gold_value' => 0,
    'oldschool' => 0,
    'inscribable' => 0,
    'modifier' => 0,
];

$failedCases = [];

function comparable(mixed $value): mixed
{
    if (is_string($value)) {
        return mb_strtolower(trim($value));
    }

    if (is_array($value)) {
        return array_map('comparable', $value);
    }

    return $value;
}

function normalizeItemText(mixed $value): mixed
{
    if (!is_string($value)) {
        return $value;
    }

    $value = mb_strtolower(trim($value));

    // Randinterpunctie is niet relevant voor item identity.
    $value = preg_replace('/^[\s:;,.\-]+|[\s:;,.\-]+$/u', '', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return $value;
}

/**
 * Alleen gebruikt om expected en actual offers aan elkaar te koppelen.
 * De score bepaalt NIET of een veld correct is.
 */
function offerMatchScore(array $expected, array $actual): int
{
    $score = 0;

    if (
        comparable($expected['trade_type'] ?? null) ===
        comparable($actual['trade_type'] ?? null)
    ) {
        $score += 20;
    }

    if (
        ($expected['requirement'] ?? null) ===
        ($actual['requirement'] ?? null)
    ) {
        $score += 40;
    }

    if (
        comparable($expected['attribute_token'] ?? null) ===
        comparable($actual['attribute_token'] ?? null)
    ) {
        $score += 35;
    }

    $expectedItem = normalizeItemText($expected['item_text'] ?? null);
    $actualItem = normalizeItemText($actual['item_text'] ?? null);

    if ($expectedItem === $actualItem) {
        $score += 50;
    } elseif (
        is_string($expectedItem) &&
        is_string($actualItem) &&
        $expectedItem !== '' &&
        $actualItem !== '' &&
        (
            str_contains($actualItem, $expectedItem) ||
            str_contains($expectedItem, $actualItem)
        )
    ) {
        $score += 10;
    }

    return $score;
}

function matchOffers(array $expected, array $actual): array
{
    $remaining = array_values($actual);
    $matches = [];

    foreach ($expected as $expectedOffer) {
        $bestIndex = null;
        $bestScore = -1;

        foreach ($remaining as $index => $actualOffer) {
            $score = offerMatchScore($expectedOffer, $actualOffer);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        if ($bestIndex === null) {
            $matches[] = [
                'expected' => $expectedOffer,
                'actual' => null,
            ];
            continue;
        }

        $matches[] = [
            'expected' => $expectedOffer,
            'actual' => $remaining[$bestIndex],
        ];

        array_splice($remaining, $bestIndex, 1);
    }

    return [
        'matches' => $matches,
        'unmatched_actual' => $remaining,
    ];
}


function classifyFieldError(string $field): string
{
    return match ($field) {
        'item_text' => 'item_identity',
        'requirement' => 'requirement',
        'attribute_token' => 'attribute',
        'trade_type' => 'trade_type',
        'price_amount', 'price_currency' => 'price',
        'gold_value' => 'gold_value',
        'oldschool' => 'oldschool',
        'inscribable' => 'inscribable',
        'mods' => 'modifier',
        default => 'item_identity',
    };
}

echo "LittyWatch AI Evaluation\n";
echo str_repeat('=', 100) . "\n";
echo "Dataset: " . basename($file) . "\n";
echo "Cases:   " . count($rows) . "\n\n";

foreach ($rows as $index => $row) {
    $messages++;

    $id = (string)($row['id'] ?? ('case-' . ($index + 1)));
    $message = (string)($row['message'] ?? '');
    $expected = $row['expected']['offers'] ?? [];

    $expectedOffers += count($expected);

    $prompt = str_replace(
        '__MESSAGE__',
        $message,
        TradeParseSchema::prompt()
    );

    echo sprintf(
        "[%d/%d] %s\n",
        $index + 1,
        count($rows),
        $id
    );

    $started = microtime(true);

    try {
        $result = $client->complete($prompt, 800, 0.0);
        $duration = microtime(true) - $started;
        $totalDuration += $duration;

        $parsed = $result['parsed'] ?? null;

        if (!is_array($parsed) || !isset($parsed['offers']) || !is_array($parsed['offers'])) {
            echo "  INVALID RESULT\n\n";
            continue;
        }

        $validJson++;

        $actual = $parsed['offers'];
        $actualOffers += count($actual);

        /*
         * Match expected offers met de meest waarschijnlijke actual offers.
         * Daarna worden de velden afzonderlijk beoordeeld.
         */
        $caseExact = count($expected) === count($actual);
        $caseErrors = [];

        if (count($expected) !== count($actual)) {
            $errorTypes['offer_count']++;
            $caseErrors['offer_count'] = true;
        }

        $matching = matchOffers($expected, $actual);

        foreach ($matching['matches'] as $match) {
            $expectedOffer = $match['expected'];
            $actualOffer = $match['actual'];

            foreach ($fields as $field) {
                $fieldScores[$field]['total']++;

                $ev = comparable($expectedOffer[$field] ?? null);
                $av = comparable($actualOffer[$field] ?? null);

                if ($field === 'item_text') {
                    $ev = normalizeItemText($expectedOffer[$field] ?? null);
                    $av = normalizeItemText($actualOffer[$field] ?? null);
                }

                if ($actualOffer !== null && $ev === $av) {
                    $fieldScores[$field]['correct']++;
                    continue;
                }

                $caseExact = false;

                $errorType = classifyFieldError($field);
                $errorTypes[$errorType]++;
                $caseErrors[$errorType] = true;
            }
        }

        if (!empty($matching['unmatched_actual'])) {
            $caseExact = false;

            if (!isset($caseErrors['offer_count'])) {
                $errorTypes['offer_count']++;
                $caseErrors['offer_count'] = true;
            }
        }

        if ($caseExact) {
            $exactMessages++;
        } else {
            $failedCases[] = [
                'id' => $id,
                'types' => array_keys($caseErrors),
            ];
        }

        printf(
            "  expected=%d actual=%d exact=%s duration=%.2fs\n",
            count($expected),
            count($actual),
            $caseExact ? 'YES' : 'NO',
            $duration
        );

        if (!$caseExact) {
            foreach ($actual as $i => $offer) {
                printf(
                    "  AI #%d %-6s | item=%-22s | q=%-4s | attr=%-12s | os=%s | ins=%s | mods=%s | price=%s%s | gv=%s\n",
                    $i + 1,
                    $offer['trade_type'] ?? '?',
                    $offer['item_text'] ?? '-',
                    isset($offer['requirement']) ? 'q' . $offer['requirement'] : '-',
                    $offer['attribute_token'] ?? '-',
                    !empty($offer['oldschool']) ? 'yes' : '-',
                    !empty($offer['inscribable']) ? 'yes' : '-',
                    implode(',', $offer['mods'] ?? []),
                    $offer['price_amount'] ?? '-',
                    $offer['price_currency'] ?? '',
                    $offer['gold_value'] ?? '-'
                );
            }
        }

        echo "\n";
    } catch (Throwable $e) {
        $duration = microtime(true) - $started;
        $totalDuration += $duration;

        $errorTypes['invalid_json']++;
        $failedCases[] = [
            'id' => $id,
            'types' => ['invalid_json'],
        ];

        echo "  ERROR: " . $e->getMessage() . "\n\n";
    }
}

echo str_repeat('=', 100) . "\n";
echo "RESULTATEN\n";
echo str_repeat('-', 100) . "\n";

printf("Messages:          %d\n", $messages);
printf("Valid JSON:        %d/%d (%.1f%%)\n",
    $validJson,
    $messages,
    $messages > 0 ? ($validJson / $messages) * 100 : 0
);

printf("Exact messages:    %d/%d (%.1f%%)\n",
    $exactMessages,
    $messages,
    $messages > 0 ? ($exactMessages / $messages) * 100 : 0
);

printf("Expected offers:   %d\n", $expectedOffers);
printf("Generated offers:  %d\n", $actualOffers);

echo "\nFIELD ACCURACY\n";

foreach ($fieldScores as $field => $score) {
    $pct = $score['total'] > 0
        ? ($score['correct'] / $score['total']) * 100
        : 0;

    printf(
        "%-18s %4d/%-4d %6.1f%%\n",
        $field,
        $score['correct'],
        $score['total'],
        $pct
    );
}

echo "\n";


echo "ERROR CLASSIFICATION" . PHP_EOL;

foreach ($errorTypes as $type => $count) {
    printf("%-20s %d\n", $type, $count);
}

echo PHP_EOL . "FAILED CASES" . PHP_EOL;

if ($failedCases === []) {
    echo "Geen." . PHP_EOL;
} else {
    foreach ($failedCases as $failed) {
        printf(
            "%-32s %s\n",
            $failed['id'],
            implode(', ', $failed['types'])
        );
    }
}

echo PHP_EOL;

printf(
    "Average latency:   %.2f sec/message\n",
    $messages > 0 ? $totalDuration / $messages : 0
);

printf(
    "Total duration:     %.2f sec\n",
    $totalDuration
);
