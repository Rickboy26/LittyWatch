<?php
declare(strict_types=1);

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $value, ?string $encoding = null): string { return strtoupper($value); }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int { return strlen($value); }
}

if (!function_exists('mb_stripos')) {
    function mb_stripos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false {
        return stripos($haystack, $needle, $offset);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string {
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }
}

require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');

$catalog = new \LittyWatch\Parser\Catalog(dirname(__DIR__) . '/app/Data');
$engine = new \LittyWatch\Parser\ParserEngine($catalog);
$failed = [];

$expectExcluded = [
    'WTS 2,500 RP to trim your guild cape in the colour of your choice! Red, Pink, Purple. 100a.',
    '[Mist Walker] is recruiting! Friendly PvE guild in an active alliance!',
    'WTS Missions: Amatz Basin',
    'PC on zealous zodiac axe of enchanting r9',
    'Hello',
];

foreach ($expectExcluded as $message) {
    $parsed = $engine->parse($message);
    if ($parsed !== []) {
        $failed[] = [
            'excluded'=>$message,
            'actual'=>array_map(fn($offer)=>$offer->toArray(), $parsed),
        ];
    }
}

$cases = [
    'WTB Q8 Flatbow' => ['item'=>'Flatbow','trade'=>'buy','requirement'=>'q8'],
    'WTB PLATINUM WAND REQ CHANNELING' => ['item'=>'Platinum Wand','trade'=>'buy','requirement'=>'any','attribute'=>'channeling magic'],
    'WTS RQ9 SILVERWING BOW INSC' => ['item'=>'Silverwing Recurve Bow','trade'=>'sell','requirement'=>'q9','inscribable'=>true],
    "WTS: Madruks Prophecy" => ['item'=>"Madruk's Prophecy",'trade'=>'sell'],
    'WTB FOCUS CORE OF APTITUDE' => ['item'=>'Focus Core of Aptitude','trade'=>'buy'],
    'WTB Staff Wrapping Of Enchantin' => ['item'=>'Staff Wrapping of Enchanting','trade'=>'buy'],
];

foreach ($cases as $message=>$expected) {
    $offers = $engine->parse($message);
    $offer = $offers[0] ?? null;

    if (!$offer) {
        $failed[] = ['missing'=>$message];
        continue;
    }

    $data = $offer->toArray();
    if (($data['item'] ?? '') !== $expected['item']) {
        $failed[] = [
            'message'=>$message,
            'field'=>'item',
            'expected'=>$expected['item'],
            'actual'=>$data,
        ];
    }
    if (($data['trade_type'] ?? '') !== $expected['trade']) {
        $failed[] = [
            'message'=>$message,
            'field'=>'trade',
            'expected'=>$expected['trade'],
            'actual'=>$data,
        ];
    }

    $modifiers = $data['modifiers'] ?? [];
    if (
        isset($expected['requirement'])
        && ($modifiers['requirement'] ?? null) !== $expected['requirement']
    ) {
        $failed[] = [
            'message'=>$message,
            'field'=>'requirement',
            'expected'=>$expected['requirement'],
            'actual'=>$data,
        ];
    }
    if (
        isset($expected['attribute'])
        && ($modifiers['attribute'] ?? null) !== $expected['attribute']
    ) {
        $failed[] = [
            'message'=>$message,
            'field'=>'attribute',
            'expected'=>$expected['attribute'],
            'actual'=>$data,
        ];
    }
    if (isset($expected['inscribable']) && empty($modifiers['inscribable'])) {
        $failed[] = [
            'message'=>$message,
            'field'=>'inscribable',
            'actual'=>$data,
        ];
    }
}

$mixed = $engine->parse('WTS Q9 Volta | WTB unded Gpriest');
$types = [];
foreach ($mixed as $offer) {
    $types[$offer->item] = $offer->tradeType;
}

if (
    ($types['Voltaic Spear'] ?? '') !== 'sell'
    || ($types['Miniature Ghostly Priest'] ?? '') !== 'buy'
) {
    $failed[] = ['mixed_trade'=>$types];
}

echo json_encode([
    'ok'=>$failed===[],
    'failed'=>$failed,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failed===[] ? 0 : 1);
