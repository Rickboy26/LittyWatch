<?php

declare(strict_types=1);

namespace LittyWatch\V2\Encyclopedia;

use PDO;
use RuntimeException;

final class ItemEncyclopediaService
{
    public function __construct(private PDO $pdo, private string $root)
    {
    }

    public function install(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS item_metadata (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_key TEXT NOT NULL UNIQUE,
    canonical_name TEXT NOT NULL,
    wiki_title TEXT,
    description TEXT,
    category TEXT,
    campaign TEXT,
    rarity TEXT,
    weapon_type TEXT,
    stackable INTEGER,
    image_url TEXT,
    local_image TEXT,
    source_url TEXT,
    source_updated_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS wiki_sync_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_key TEXT NOT NULL,
    wiki_title TEXT,
    status TEXT NOT NULL,
    message TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_item_metadata_name ON item_metadata(canonical_name)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_wiki_sync_item ON wiki_sync_log(item_key, created_at)');
    }

    /** @return array<int,array<string,mixed>> */
    public function items(string $query = '', int $limit = 250): array
    {
        $this->install();

        $where = [];
        $params = [];
        $query = trim($query);
        if ($query !== '') {
            $where[] = '(catalog.item LIKE :query OR catalog.item_key LIKE :query OR im.description LIKE :query)';
            $params[':query'] = '%' . $query . '%';
        }

        $structured = $this->tableExists('structured_offers');
        if (!$structured) {
            return [];
        }

        $marketExpr = $this->columnExists('structured_offers', 'normalized_market_key')
            ? "COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key)"
            : 'so.market_key';

        $sql = <<<SQL
WITH catalog AS (
    SELECT
        MIN(so.item_key) AS item_key,
        so.item,
        COUNT(*) AS offers_count,
        COUNT(DISTINCT {$marketExpr}) AS market_count,
        MAX(m.posted_at) AS last_activity
    FROM structured_offers so
    JOIN messages m ON m.id = so.message_id
    WHERE TRIM(COALESCE(so.item, '')) <> ''
    GROUP BY so.item
)
SELECT
    catalog.*,
    im.wiki_title,
    im.description,
    im.category,
    im.campaign,
    im.rarity,
    im.weapon_type,
    im.stackable,
    im.image_url,
    im.local_image,
    im.source_url,
    im.source_updated_at
FROM catalog
LEFT JOIN item_metadata im ON im.item_key = catalog.item_key
%s
ORDER BY
    CASE WHEN im.local_image IS NOT NULL THEN 0 ELSE 1 END,
    catalog.offers_count DESC,
    catalog.item COLLATE NOCASE ASC
LIMIT :limit
SQL;

        $sql = sprintf($sql, $where ? 'WHERE ' . implode(' AND ', $where) : '');
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function item(string $itemKey): ?array
    {
        $this->install();

        if (!$this->tableExists('structured_offers')) {
            return null;
        }

        $marketExpr = $this->columnExists('structured_offers', 'normalized_market_key')
            ? "COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key)"
            : 'so.market_key';

        $stmt = $this->pdo->prepare(<<<SQL
SELECT
    MIN(so.item_key) AS item_key,
    MIN(so.item) AS item,
    COUNT(*) AS offers_count,
    COUNT(DISTINCT {$marketExpr}) AS market_count,
    COUNT(DISTINCT NULLIF(m.player, '')) AS trader_count,
    MAX(m.posted_at) AS last_activity,
    im.wiki_title,
    im.description,
    im.category,
    im.campaign,
    im.rarity,
    im.weapon_type,
    im.stackable,
    im.image_url,
    im.local_image,
    im.source_url,
    im.source_updated_at
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
LEFT JOIN item_metadata im ON im.item_key = so.item_key
WHERE so.item_key = :item_key
GROUP BY so.item_key
LIMIT 1
SQL);
        $stmt->execute([':item_key' => $itemKey]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function markets(string $itemKey, int $limit = 100): array
    {
        if (!$this->tableExists('market_intelligence')) {
            return [];
        }

        $stmt = $this->pdo->prepare(<<<'SQL'
SELECT *
FROM market_intelligence
WHERE market_key IN (
    SELECT DISTINCT COALESCE(NULLIF(normalized_market_key, ''), market_key)
    FROM structured_offers
    WHERE item_key = :item_key
)
ORDER BY unique_traders DESC, last_activity DESC
LIMIT :limit
SQL);
        $stmt->bindValue(':item_key', $itemKey, PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed> */
    public function sync(string $itemKey, ?string $wikiTitle = null): array
    {
        $this->install();

        $item = $this->item($itemKey);
        if ($item === null) {
            throw new RuntimeException('Item niet gevonden in structured offers.');
        }

        $title = trim((string)($wikiTitle ?: $item['item']));
        if ($title === '') {
            throw new RuntimeException('Geen geldige Wiki-titel.');
        }

        $api = 'https://wiki.guildwars.com/api.php';
        $query = http_build_query([
            'action' => 'query',
            'format' => 'json',
            'formatversion' => '2',
            'redirects' => '1',
            'prop' => 'extracts|pageimages|info',
            'exintro' => '1',
            'explaintext' => '1',
            'piprop' => 'original',
            'inprop' => 'url',
            'titles' => $title,
            'origin' => '*',
        ]);

        $payload = $this->httpGet($api . '?' . $query);
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $page = $data['query']['pages'][0] ?? null;

        if (!is_array($page) || isset($page['missing'])) {
            $this->log($itemKey, $title, 'not_found', 'Pagina niet gevonden.');
            return ['ok' => false, 'status' => 'not_found', 'title' => $title];
        }

        $canonicalTitle = (string)($page['title'] ?? $title);
        $description = trim((string)($page['extract'] ?? ''));
        $imageUrl = (string)($page['original']['source'] ?? '');
        $sourceUrl = (string)($page['fullurl'] ?? '');

        $localImage = null;
        if ($imageUrl !== '') {
            $localImage = $this->cacheImage($itemKey, $imageUrl);
        }

        $stmt = $this->pdo->prepare(<<<'SQL'
INSERT INTO item_metadata (
    item_key, canonical_name, wiki_title, description,
    image_url, local_image, source_url, source_updated_at, updated_at
) VALUES (
    :item_key, :canonical_name, :wiki_title, :description,
    :image_url, :local_image, :source_url, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
)
ON CONFLICT(item_key) DO UPDATE SET
    canonical_name = excluded.canonical_name,
    wiki_title = excluded.wiki_title,
    description = excluded.description,
    image_url = excluded.image_url,
    local_image = COALESCE(excluded.local_image, item_metadata.local_image),
    source_url = excluded.source_url,
    source_updated_at = CURRENT_TIMESTAMP,
    updated_at = CURRENT_TIMESTAMP
SQL);

        $stmt->execute([
            ':item_key' => $itemKey,
            ':canonical_name' => (string)$item['item'],
            ':wiki_title' => $canonicalTitle,
            ':description' => $description,
            ':image_url' => $imageUrl !== '' ? $imageUrl : null,
            ':local_image' => $localImage,
            ':source_url' => $sourceUrl !== '' ? $sourceUrl : null,
        ]);

        $this->log($itemKey, $canonicalTitle, 'success', 'Metadata bijgewerkt.');

        return [
            'ok' => true,
            'status' => 'success',
            'item_key' => $itemKey,
            'wiki_title' => $canonicalTitle,
            'description_length' => mb_strlen($description),
            'image_cached' => $localImage !== null,
            'local_image' => $localImage,
        ];
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $this->install();
        $catalog = count($this->items('', 1000));
        $metadata = (int)$this->pdo->query('SELECT COUNT(*) FROM item_metadata')->fetchColumn();
        $images = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM item_metadata WHERE local_image IS NOT NULL AND local_image <> ''"
        )->fetchColumn();
        $failed = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM wiki_sync_log WHERE status <> 'success'"
        )->fetchColumn();

        return [
            'catalog_items' => $catalog,
            'metadata_items' => $metadata,
            'cached_images' => $images,
            'failed_syncs' => $failed,
        ];
    }

    private function httpGet(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL ontbreekt op de server.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'LittyWatch/2.7 (+Guild Wars market encyclopedia)',
        ]);

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Wiki-opvraag mislukt: HTTP ' . $status . ($error ? ' · ' . $error : ''));
        }

        return $body;
    }

    private function cacheImage(string $itemKey, string $url): ?string
    {
        $extension = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            $extension = 'png';
        }

        $relative = 'assets/items/' . preg_replace('/[^a-z0-9_-]+/i', '-', $itemKey) . '.' . $extension;
        $absolute = $this->root . '/' . $relative;
        $directory = dirname($absolute);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }

        $body = $this->httpGet($url);
        if (file_put_contents($absolute, $body, LOCK_EX) === false) {
            return null;
        }

        return '/' . $relative;
    }

    private function log(string $itemKey, string $title, string $status, string $message): void
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
INSERT INTO wiki_sync_log (item_key, wiki_title, status, message)
VALUES (:item_key, :wiki_title, :status, :message)
SQL);
        $stmt->execute([
            ':item_key' => $itemKey,
            ':wiki_title' => $title,
            ':status' => $status,
            ':message' => $message,
        ]);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
        $stmt->execute([':name' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        foreach ($this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }
        return false;
    }
}
