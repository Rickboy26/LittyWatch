<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$item = trim((string)($_GET['item'] ?? ''));
$size = max(32, min(256, (int)($_GET['size'] ?? 64)));
$assetRoot = __DIR__ . '/assets/game-items';

$placeholder = static function () use ($item): never {
    $initials = '';
    foreach (preg_split('/\s+/u', trim($item)) ?: [] as $word) {
        if ($word !== '') $initials .= mb_substr($word, 0, 1);
        if (mb_strlen($initials) >= 2) break;
    }
    $initials = strtoupper($initials ?: '?');
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=1800');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#211b11"/><stop offset="1" stop-color="#07131b"/></linearGradient></defs><rect width="128" height="128" rx="18" fill="url(#g)"/><rect x="7" y="7" width="114" height="114" rx="14" fill="none" stroke="#b89447" stroke-opacity=".35"/><text x="64" y="76" text-anchor="middle" font-size="34" font-family="Georgia,serif" fill="#e4ca85">'.htmlspecialchars($initials, ENT_QUOTES).'</text></svg>';
    exit;
};

$serve = static function (string $path): never {
    if (!is_file($path) || !is_readable($path) || filesize($path) < 32) {
        http_response_code(404);
        exit;
    }
    $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'webp' => 'image/webp',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        default => 'image/png',
    };
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=604800, immutable');
    header('Content-Length: ' . (string)filesize($path));
    readfile($path);
    exit;
};

$normalizeLocalPath = static function (?string $webPath): ?string {
    if ($webPath === null || trim($webPath) === '') return null;
    $relative = ltrim(str_replace('\\', '/', trim($webPath)), '/');
    if (!str_starts_with($relative, 'assets/game-items/')) return null;
    if (str_contains($relative, '../')) return null;
    $path = __DIR__ . '/' . $relative;
    return is_file($path) ? $path : null;
};

$findByFilename = static function (string $filename) use ($assetRoot): ?string {
    $filename = basename($filename);
    if ($filename === '' || !is_dir($assetRoot)) return null;
    $direct = $assetRoot . '/' . $filename;
    if (is_file($direct)) return $direct;
    $inventory = $assetRoot . '/inventory/' . $filename;
    if (is_file($inventory)) return $inventory;
    foreach (glob($assetRoot . '/*/' . $filename) ?: [] as $candidate) {
        if (is_file($candidate)) return $candidate;
    }
    return null;
};

$findByDatId = static function (int $id) use ($assetRoot): ?string {
    if ($id <= 0 || !is_dir($assetRoot)) return null;
    $patterns = [
        'item_icon_' . $id . '.*',
        'itemIcon_' . $id . '.*',
        'item-icon-' . $id . '.*',
    ];
    foreach ($patterns as $pattern) {
        foreach ([$assetRoot . '/' . $pattern, $assetRoot . '/inventory/' . $pattern, $assetRoot . '/*/' . $pattern] as $globPattern) {
            foreach (glob($globPattern) ?: [] as $candidate) {
                if (is_file($candidate)) return $candidate;
            }
        }
    }
    return null;
};

if ($item === '') $placeholder();

// 1. Primary source: local Gw.dat inventory-icon catalog. This works with the
// existing item_assets table and keeps the site completely independent of Wiki
// thumbnails. linked_item_* is preferred; source_name is a useful fallback for
// imports that happened before the market item existed.
try {
    $pdo = db();
    $hasAssets = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='item_assets' LIMIT 1")->fetchColumn();
    if ($hasAssets) {
        $itemKey = null;
        $hasStructured = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='structured_offers' LIMIT 1")->fetchColumn();
        if ($hasStructured) {
            $st = $pdo->prepare("SELECT item_key FROM structured_offers WHERE lower(trim(item))=lower(trim(:item)) AND trim(COALESCE(item_key,''))<>'' ORDER BY id DESC LIMIT 1");
            $st->execute([':item' => $item]);
            $itemKey = $st->fetchColumn() ?: null;
        }

        $sql = "SELECT web_path,relative_path,source_filename,dat_file_id,linked_item_key,linked_item_name,source_name
                FROM item_assets
                WHERE lower(trim(COALESCE(linked_item_name,'')))=lower(trim(:item))
                   OR lower(trim(COALESCE(source_name,'')))=lower(trim(:item))";
        $params = [':item' => $item];
        if (is_string($itemKey) && $itemKey !== '') {
            $sql .= " OR linked_item_key=:item_key";
            $params[':item_key'] = $itemKey;
        }
        $sql .= " ORDER BY CASE WHEN linked_item_key IS NOT NULL AND linked_item_key<>'' THEN 0 ELSE 1 END, updated_at DESC, id DESC LIMIT 8";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        foreach ($st->fetchAll() as $asset) {
            $path = $normalizeLocalPath(isset($asset['web_path']) ? (string)$asset['web_path'] : null)
                ?? $normalizeLocalPath(isset($asset['relative_path']) ? '/' . ltrim((string)$asset['relative_path'], '/') : null);
            if ($path !== null) $serve($path);
            $filename = trim((string)($asset['source_filename'] ?? ''));
            if ($filename !== '' && ($path = $findByFilename($filename)) !== null) $serve($path);
            $datId = (int)($asset['dat_file_id'] ?? 0);
            if ($datId > 0 && ($path = $findByDatId($datId)) !== null) $serve($path);
        }
    }
} catch (Throwable) {
    // Images must never break the page when the DB is temporarily unavailable.
}

// 2. Optional tiny name -> DAT id override file. Useful for manually fixing a
// handful of ambiguous/shared icons without touching database records.
$manualMapFile = __DIR__ . '/config/item-icons.php';
if (is_file($manualMapFile)) {
    $manual = require $manualMapFile;
    if (is_array($manual)) {
        $id = isset($manual[$item]) ? (int)$manual[$item] : 0;
        if ($id > 0 && ($path = $findByDatId($id)) !== null) $serve($path);
    }
}

// No external Wiki request here by design: LittyWatch now prefers authentic
// inventory icons and otherwise shows a neutral placeholder until linked.
$placeholder();
