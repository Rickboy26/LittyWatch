<?php
declare(strict_types=1);

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}
if (!function_exists('mb_stripos')) {
    function mb_stripos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false {
        return stripos($haystack, $needle, $offset);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int { return strlen($value); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string {
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }
}

require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');

$catalog = new \LittyWatch\Parser\Catalog(dirname(__DIR__) . '/app/Data');
$matcher = new \LittyWatch\Parser\ItemMatcher($catalog);
$failed = [];

$match = $matcher->matchAll('WTS eblade q9');
if (!$match || ($match[0]['item'] ?? '') !== 'Eternal Blade') {
    $failed[] = ['alias'=>'eblade','actual'=>$match];
}

echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[] ? 0 : 1);
