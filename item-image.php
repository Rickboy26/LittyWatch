<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$item = trim((string)($_GET['item'] ?? ''));
$size = max(32, min(256, (int)($_GET['size'] ?? 64)));
$map = require __DIR__ . '/config/item-images.php';
$title = is_array($map) ? ($map[$item] ?? null) : null;
$cacheDir = __DIR__ . '/assets/items';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
$slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $item), '-')) ?: 'unknown';
$metaFile = $cacheDir . '/' . $slug . '.json';

$placeholder = static function () use ($item): never {
    $initials = '';
    foreach (preg_split('/\s+/u', trim($item)) ?: [] as $word) {
        if ($word !== '') $initials .= mb_substr($word, 0, 1);
        if (mb_strlen($initials) >= 2) break;
    }
    $initials = strtoupper($initials ?: '?');
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#302812"/><stop offset="1" stop-color="#0d1723"/></linearGradient></defs><rect width="128" height="128" rx="24" fill="url(#g)"/><path d="M18 89L64 18l46 71-46 22z" fill="none" stroke="#c6a75e" stroke-width="4" opacity=".55"/><text x="64" y="75" text-anchor="middle" font-size="35" font-family="Georgia,serif" fill="#f3dfaa">'.htmlspecialchars($initials, ENT_QUOTES).'</text></svg>';
    exit;
};

if (!$title) $placeholder();

$cached = null;
foreach (['webp','png','jpg','jpeg','gif'] as $ext) {
    $path = $cacheDir . '/' . $slug . '.' . $ext;
    if (is_file($path) && filesize($path) > 100) { $cached = $path; break; }
}
if ($cached) {
    $mime = mime_content_type($cached) ?: 'image/png';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=604800, immutable');
    readfile($cached); exit;
}

if (!extension_loaded('curl')) $placeholder();
$api = 'https://wiki.guildwars.com/api.php?' . http_build_query([
    'action' => 'query', 'prop' => 'pageimages', 'piprop' => 'thumbnail|original',
    'pithumbsize' => max(128, $size * 2), 'titles' => $title, 'format' => 'json',
]);
$ch = curl_init($api);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>12,CURLOPT_USERAGENT=>'LittyWatch/1.9.5 (+https://hollandseglory.nl)']);
$json = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
if (!is_string($json) || $status < 200 || $status >= 300) $placeholder();
$data = json_decode($json, true);
$page = is_array($data) ? reset($data['query']['pages']) : null;
$imageUrl = is_array($page) ? ($page['thumbnail']['source'] ?? $page['original']['source'] ?? null) : null;
if (!is_string($imageUrl) || !str_starts_with($imageUrl, 'https://wiki.guildwars.com/')) $placeholder();
$ch = curl_init($imageUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'LittyWatch/1.9.5 (+https://hollandseglory.nl)']);
$bytes = curl_exec($ch); $mime = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
if (!is_string($bytes) || strlen($bytes) < 100 || $status < 200 || $status >= 300 || !str_starts_with($mime, 'image/')) $placeholder();
$ext = match (true) { str_contains($mime,'webp')=>'webp', str_contains($mime,'jpeg')=>'jpg', str_contains($mime,'gif')=>'gif', default=>'png' };
$path = $cacheDir . '/' . $slug . '.' . $ext;
@file_put_contents($path, $bytes, LOCK_EX);
@file_put_contents($metaFile, json_encode(['item'=>$item,'wiki_title'=>$title,'source_page'=>'https://wiki.guildwars.com/wiki/'.rawurlencode(str_replace(' ','_',$title)),'image_url'=>$imageUrl,'cached_at'=>date(DATE_ATOM)], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=604800, immutable');
echo $bytes;
