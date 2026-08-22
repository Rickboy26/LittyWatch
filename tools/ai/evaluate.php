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

foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = max(1, (int)$m[1]);
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

    $rows[] = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

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

function offerKey(array $offer): string
{
    return implode('|', [
        comparable($offer['trade_type'] ?? null) ?? '-',
        comparable($offer['item_text'] ?? null) ?? '-',
        (string)($offer['requirement'] ?? '-'),
        comparable($offer['attribute_token'] ?? null) ?? '-',
    ]);
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
        $result = $client->complete($prompt, 400, 0.0);
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
         * Match offers by structural identity instead of assuming
         * generation order is always identical.
         */
        $actualBuckets = [];

        foreach ($actual as $offer) {
            $actualBuckets[offerKey($offer)][] = $offer;
        }

        $caseExact = count($expected) === count($actual);

        foreach ($expected as $expectedOffer) {
            $key = offerKey($expectedOffer);
            $actualOffer = null;

            if (!empty($actualBuckets[$key])) {
                $actualOffer = array_shift($actualBuckets[$key]);
            }

            foreach ($fields as $field) {
                $fieldScores[$field]['total']++;

                $ev = comparable($expectedOffer[$field] ?? null);
                $av = comparable($actualOffer[$field] ?? null);

                if ($actualOffer !== null && $ev === $av) {
                    $fieldScores[$field]['correct']++;
                } else {
                    $caseExact = false;
                }
            }
        }

        if ($caseExact) {
            $exactMessages++;
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

printf(
    "Average latency:   %.2f sec/message\n",
    $messages > 0 ? $totalDuration / $messages : 0
);

printf(
    "Total duration:     %.2f sec\n",
    $totalDuration
);
