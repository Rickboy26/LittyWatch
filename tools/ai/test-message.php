<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use LittyWatch\AI\LocalAiClient;

$message = $argv[1] ?? null;

if ($message === null || trim($message) === '') {
    fwrite(STDERR, "Gebruik:\n");
    fwrite(STDERR, "php tools/ai/test-message.php 'WTS ...'\n");
    exit(1);
}

$prompt = <<<'PROMPT'
/no_think
You are LittyWatch's Guild Wars 1 Kamadan trade interpreter.

Extract ALL offers and variants from the MESSAGE.

Rules:
- WTS = sell
- WTB = buy
- WTT = trade
- q9/r9 means requirement 9
- A requirement followed by multiple attribute tokens creates multiple variants.
- Variants without a repeated item name inherit the previous item.
- Variants without a repeated requirement may inherit the current requirement.
- Keep Guild Wars attribute shorthand exactly as written.
- Keep item_text close to the text in the message.
- Do not invent missing values.
- null means the value is genuinely absent.
- offers may only be empty when there is no trade offer in the message.

Example 1:
MESSAGE:
WTS frog q9 insp SR // q11 es q13 FC q13 spaw

OUTPUT:
{"offers":[
{"trade_type":"sell","item_text":"frog","requirement":9,"attribute_token":"insp","price_amount":null,"price_currency":null},
{"trade_type":"sell","item_text":"frog","requirement":9,"attribute_token":"SR","price_amount":null,"price_currency":null},
{"trade_type":"sell","item_text":"frog","requirement":11,"attribute_token":"es","price_amount":null,"price_currency":null},
{"trade_type":"sell","item_text":"frog","requirement":13,"attribute_token":"FC","price_amount":null,"price_currency":null},
{"trade_type":"sell","item_text":"frog","requirement":13,"attribute_token":"spaw","price_amount":null,"price_currency":null}
]}

Example 2:
MESSAGE:
WTS Ghostly Staff q13 channeling 10e - q11 dom 1a

OUTPUT:
{"offers":[
{"trade_type":"sell","item_text":"Ghostly Staff","requirement":13,"attribute_token":"channeling","price_amount":10,"price_currency":"e"},
{"trade_type":"sell","item_text":"Ghostly Staff","requirement":11,"attribute_token":"dom","price_amount":1,"price_currency":"a"}
]}

Example 3:
MESSAGE:
WTB BDS q11 dom/air/FC/insp

OUTPUT:
{"offers":[
{"trade_type":"buy","item_text":"BDS","requirement":11,"attribute_token":"dom","price_amount":null,"price_currency":null},
{"trade_type":"buy","item_text":"BDS","requirement":11,"attribute_token":"air","price_amount":null,"price_currency":null},
{"trade_type":"buy","item_text":"BDS","requirement":11,"attribute_token":"FC","price_amount":null,"price_currency":null},
{"trade_type":"buy","item_text":"BDS","requirement":11,"attribute_token":"insp","price_amount":null,"price_currency":null}
]}

Now parse this message.

MESSAGE:
__MESSAGE__

OUTPUT:
PROMPT;

$prompt = str_replace('__MESSAGE__', $message, $prompt);

$client = new LocalAiClient();

$started = microtime(true);

try {
    $result = $client->complete($prompt, 2048, 0.0);
} catch (Throwable $e) {
    fwrite(STDERR, "AI ERROR: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$duration = microtime(true) - $started;
$content = $result['content'];

echo str_repeat('=', 100) . PHP_EOL;
echo "MESSAGE" . PHP_EOL;
echo str_repeat('-', 100) . PHP_EOL;
echo $message . PHP_EOL . PHP_EOL;

echo "AI RAW OUTPUT" . PHP_EOL;
echo str_repeat('-', 100) . PHP_EOL;
echo $content . PHP_EOL . PHP_EOL;

$json = json_decode($content, true);

echo "VALIDATION" . PHP_EOL;
echo str_repeat('-', 100) . PHP_EOL;

if (!is_array($json)) {
    echo "JSON: INVALID" . PHP_EOL;
    echo "Error: " . json_last_error_msg() . PHP_EOL;
    exit(2);
}

if (!isset($json['offers']) || !is_array($json['offers'])) {
    echo "JSON: VALID" . PHP_EOL;
    echo "Schema: INVALID (offers ontbreekt)" . PHP_EOL;
    exit(3);
}

echo "JSON:   VALID" . PHP_EOL;
echo "Offers: " . count($json['offers']) . PHP_EOL;
echo "Duur:   " . number_format($duration, 2) . " sec" . PHP_EOL;

echo PHP_EOL . "INTERPRETATION" . PHP_EOL;
echo str_repeat('-', 100) . PHP_EOL;

foreach ($json['offers'] as $i => $offer) {
    printf(
        "#%-2d %-7s | item=%-20s | q=%-4s | attr=%-10s | price=%s%s\n",
        $i + 1,
        $offer['trade_type'] ?? '?',
        $offer['item_text'] ?? '?',
        isset($offer['requirement']) ? 'q' . $offer['requirement'] : '-',
        $offer['attribute_token'] ?? '-',
        $offer['price_amount'] ?? '-',
        $offer['price_currency'] ?? ''
    );
}
