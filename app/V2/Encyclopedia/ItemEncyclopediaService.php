<?php

declare(strict_types=1);

namespace LittyWatch\V2\Encyclopedia;

use PDO;
use RuntimeException;

final class ItemEncyclopediaService
{
    private WikiClient $wiki;

    public function __construct(private PDO $pdo, private string $root)
    {
        $this->wiki = new WikiClient();
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
    source_transport TEXT,
    source_updated_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

        // Compatibility for databases created by V2.7.
        if (!$this->columnExists('item_metadata', 'source_transport')) {
            $this->pdo->exec('ALTER TABLE item_metadata ADD COLUMN source_transport TEXT');
        }

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

        //  local Gw.dat asset catalog.
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS item_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, import_id INTEGER NOT NULL, dat_file_id INTEGER, source_filename TEXT NOT NULL, relative_path TEXT NOT NULL, web_path TEXT NOT NULL, sha256 TEXT NOT NULL UNIQUE, bytes INTEGER, width INTEGER, height INTEGER, source_model_id INTEGER, source_name TEXT, source_type TEXT, source_rarity TEXT, linked_item_key TEXT, linked_item_name TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_item_assets_link ON item_assets(linked_item_key)');
    }

    /** @return array<int,array<string,mixed>> */
    public function items(string $query = '', int $limit = 250): array
    {
        $this->install();
        if (!$this->tableExists('structured_offers')) {
            return [];
        }

        $where = [];
        $params = [];
        if (trim($query) !== '') {
            $where[] = '(catalog.item LIKE :query OR catalog.item_key LIKE :query OR im.description LIKE :query)';
            $params[':query'] = '%' . trim($query) . '%';
        }

        $marketExpr = $this->columnExists('structured_offers', 'normalized_market_key')
            ? "COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key)"
            : 'so.market_key';

        $sql = sprintf(<<<SQL
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
    im.wiki_title, im.description, im.category, im.campaign, im.rarity,
    im.weapon_type, im.stackable, im.image_url,
    COALESCE(
        NULLIF(im.local_image, ''),
        (SELECT ia.web_path FROM item_assets ia WHERE ia.linked_item_key = catalog.item_key ORDER BY ia.updated_at DESC, ia.id DESC LIMIT 1)
    ) AS local_image,
    im.source_url, im.source_transport, im.source_updated_at
FROM catalog
LEFT JOIN item_metadata im ON im.item_key = catalog.item_key
%s
ORDER BY
    CASE WHEN im.local_image IS NOT NULL THEN 0 ELSE 1 END,
    catalog.offers_count DESC,
    catalog.item COLLATE NOCASE ASC
LIMIT :limit
SQL, $where ? 'WHERE ' . implode(' AND ', $where) : '');

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
    im.wiki_title, im.description, im.category, im.campaign, im.rarity,
    im.weapon_type, im.stackable, im.image_url,
    COALESCE(
        NULLIF(im.local_image, ''),
        (SELECT ia.web_path FROM item_assets ia WHERE ia.linked_item_key = so.item_key ORDER BY ia.updated_at DESC, ia.id DESC LIMIT 1)
    ) AS local_image,
    im.source_url, im.source_transport, im.source_updated_at
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
        $page = $this->wiki->page($title);
        $localImage = null;

        if (trim((string)$page['image_url']) !== '') {
            try {
                $localImage = $this->cacheImage($itemKey, (string)$page['image_url']);
            } catch (\Throwable $e) {
                $this->log($itemKey, $title, 'image_error', $e->getMessage());
            }
        }

        $stmt = $this->pdo->prepare(<<<'SQL'
INSERT INTO item_metadata (
    item_key, canonical_name, wiki_title, description,
    image_url, local_image, source_url, source_transport,
    source_updated_at, updated_at
) VALUES (
    :item_key, :canonical_name, :wiki_title, :description,
    :image_url, :local_image, :source_url, :source_transport,
    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
)
ON CONFLICT(item_key) DO UPDATE SET
    canonical_name = excluded.canonical_name,
    wiki_title = excluded.wiki_title,
    description = excluded.description,
    image_url = excluded.image_url,
    local_image = COALESCE(excluded.local_image, item_metadata.local_image),
    source_url = excluded.source_url,
    source_transport = excluded.source_transport,
    source_updated_at = CURRENT_TIMESTAMP,
    updated_at = CURRENT_TIMESTAMP
SQL);
        $stmt->execute([
            ':item_key' => $itemKey,
            ':canonical_name' => (string)$item['item'],
            ':wiki_title' => (string)$page['title'],
            ':description' => (string)$page['description'],
            ':image_url' => trim((string)$page['image_url']) !== '' ? $page['image_url'] : null,
            ':local_image' => $localImage,
            ':source_url' => trim((string)$page['source_url']) !== '' ? $page['source_url'] : null,
            ':source_transport' => (string)$page['transport'],
        ]);

        $this->log($itemKey, (string)$page['title'], 'success', 'Metadata via ' . $page['transport'] . ' bijgewerkt.');

        return [
            'ok' => true,
            'status' => 'success',
            'item_key' => $itemKey,
            'wiki_title' => (string)$page['title'],
            'transport' => (string)$page['transport'],
            'api_error' => $page['api_error'] ?? null,
            'description_length' => mb_strlen((string)$page['description']),
            'image_cached' => $localImage !== null,
            'local_image' => $localImage,
        ];
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $this->install();
        return [
            'catalog_items' => count($this->items('', 1000)),
            'metadata_items' => (int)$this->pdo->query('SELECT COUNT(*) FROM item_metadata')->fetchColumn(),
            'cached_images' => (int)$this->pdo->query(<<<'SQL'
SELECT COUNT(*) FROM (
    SELECT item_key FROM item_metadata WHERE local_image IS NOT NULL AND local_image <> ''
    UNION
    SELECT linked_item_key FROM item_assets WHERE linked_item_key IS NOT NULL AND linked_item_key <> ''
)
SQL)->fetchColumn(),
            'failed_syncs' => (int)$this->pdo->query(
                "SELECT COUNT(*) FROM wiki_sync_log WHERE status <> 'success'"
            )->fetchColumn(),
        ];
    }

    private function cacheImage(string $itemKey, string $url): ?string
    {
        $extension = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            $extension = 'png';
        }

        $relative = 'assets/items/' . preg_replace('/[^a-z0-9_-]+/i', '-', $itemKey) . '.' . $extension;
        $absolute = $this->root . '/' . $relative;
        if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0775, true) && !is_dir(dirname($absolute))) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'LittyWatch/2.7.1 (Guild Wars item image cache)',
            CURLOPT_REFERER => 'https://wiki.guildwars.com/wiki/Item',
            CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/png,image/jpeg,image/*,*/*;q=0.8'],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Afbeelding ophalen mislukt: HTTP ' . $status . ($error ? ' · ' . $error : ''));
        }

        return file_put_contents($absolute, $body, LOCK_EX) !== false ? '/' . $relative : null;
    }

    private function log(string $itemKey, string $title, string $status, string $message): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO wiki_sync_log (item_key, wiki_title, status, message)
             VALUES (:item_key, :wiki_title, :status, :message)'
        );
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
