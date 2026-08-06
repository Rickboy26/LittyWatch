<?php

declare(strict_types=1);

namespace LittyWatch\V2\Encyclopedia;

use PDO;
use RuntimeException;

final class CatalogImportService
{
    public function __construct(
        private PDO $pdo,
        private WikiClient $wiki
    ) {
    }

    public function install(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS wiki_catalog_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    wiki_title TEXT NOT NULL UNIQUE,
    item_key TEXT NOT NULL,
    source_category TEXT NOT NULL,
    member_type TEXT NOT NULL DEFAULT 'page',
    import_status TEXT NOT NULL DEFAULT 'discovered',
    linked_item_key TEXT,
    discovered_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS wiki_catalog_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_title TEXT NOT NULL UNIQUE,
    parent_category TEXT,
    depth INTEGER NOT NULL DEFAULT 0,
    import_status TEXT NOT NULL DEFAULT 'discovered',
    page_count INTEGER NOT NULL DEFAULT 0,
    subcategory_count INTEGER NOT NULL DEFAULT 0,
    discovered_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_catalog_item_key ON wiki_catalog_items(item_key)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_catalog_source_category ON wiki_catalog_items(source_category)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_catalog_category_status ON wiki_catalog_categories(import_status)');
    }

    /** @return array<string,mixed> */
    public function importCategory(
        string $categoryTitle,
        bool $includeSubcategories = true,
        int $depth = 0,
        int $maxDepth = 1,
        int $maxPages = 10
    ): array {
        $this->install();

        if (!str_starts_with(mb_strtolower($categoryTitle), 'category:')) {
            $categoryTitle = 'Category:' . trim($categoryTitle);
        }

        $result = $this->wiki->category($categoryTitle, $maxPages);
        $pagesAdded = 0;
        $categoriesAdded = 0;

        $this->upsertCategory(
            $categoryTitle,
            null,
            $depth,
            'imported',
            count($result['pages']),
            count($result['subcategories'])
        );

        foreach ($result['pages'] as $page) {
            $title = trim((string)($page['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $this->upsertPage($title, $categoryTitle, 'page');
            $pagesAdded++;
        }

        foreach ($result['subcategories'] as $subcategory) {
            $title = trim((string)($subcategory['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $this->upsertCategory($title, $categoryTitle, $depth + 1, 'discovered', 0, 0);
            $categoriesAdded++;
        }

        $children = [];
        if ($includeSubcategories && $depth < $maxDepth) {
            foreach ($result['subcategories'] as $subcategory) {
                $title = trim((string)($subcategory['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                try {
                    $children[] = $this->importCategory(
                        $title,
                        true,
                        $depth + 1,
                        $maxDepth,
                        $maxPages
                    );
                } catch (\Throwable $e) {
                    $this->markCategoryError($title, $e->getMessage());
                    $children[] = [
                        'category' => $title,
                        'ok' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'ok' => true,
            'category' => $categoryTitle,
            'transport' => $result['transport'],
            'pages_found' => count($result['pages']),
            'subcategories_found' => count($result['subcategories']),
            'pages_saved' => $pagesAdded,
            'categories_saved' => $categoriesAdded,
            'depth' => $depth,
            'children' => $children,
        ];
    }

    /** @return array<string,int> */
    public function linkToMarketCatalog(): array
    {
        $this->install();
        if (!$this->tableExists('structured_offers')) {
            return ['linked' => 0, 'unlinked' => 0];
        }

        $marketItems = $this->pdo->query(
            "SELECT DISTINCT item_key, item FROM structured_offers
             WHERE TRIM(COALESCE(item_key, '')) <> ''"
        )->fetchAll();

        $linked = 0;
        foreach ($marketItems as $item) {
            $itemKey = trim((string)$item['item_key']);
            $canonical = trim((string)$item['item']);
            $stmt = $this->pdo->prepare(<<<'SQL'
UPDATE wiki_catalog_items
SET linked_item_key = :item_key,
    import_status = 'linked',
    updated_at = CURRENT_TIMESTAMP
WHERE item_key = :item_key
   OR LOWER(wiki_title) = LOWER(:canonical)
SQL);
            $stmt->execute([
                ':item_key' => $itemKey,
                ':canonical' => $canonical,
            ]);
            $linked += $stmt->rowCount();
        }

        $unlinked = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM wiki_catalog_items WHERE linked_item_key IS NULL"
        )->fetchColumn();

        return ['linked' => $linked, 'unlinked' => $unlinked];
    }

    /** @return array<int,array<string,mixed>> */
    public function categories(int $limit = 300): array
    {
        $this->install();
        $stmt = $this->pdo->prepare(
            'SELECT * FROM wiki_catalog_categories ORDER BY depth, category_title LIMIT :limit'
        );
        $stmt->bindValue(':limit', max(1, min(2000, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function items(string $query = '', int $limit = 300): array
    {
        $this->install();
        $where = '';
        $params = [];
        if (trim($query) !== '') {
            $where = 'WHERE wiki_title LIKE :query OR item_key LIKE :query OR source_category LIKE :query';
            $params[':query'] = '%' . trim($query) . '%';
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM wiki_catalog_items {$where}
             ORDER BY CASE WHEN linked_item_key IS NOT NULL THEN 0 ELSE 1 END, wiki_title
             LIMIT :limit"
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(3000, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $this->install();

        return [
            'categories' => (int)$this->pdo->query('SELECT COUNT(*) FROM wiki_catalog_categories')->fetchColumn(),
            'items' => (int)$this->pdo->query('SELECT COUNT(*) FROM wiki_catalog_items')->fetchColumn(),
            'linked_items' => (int)$this->pdo->query(
                "SELECT COUNT(*) FROM wiki_catalog_items WHERE linked_item_key IS NOT NULL"
            )->fetchColumn(),
            'pending_categories' => (int)$this->pdo->query(
                "SELECT COUNT(*) FROM wiki_catalog_categories WHERE import_status = 'discovered'"
            )->fetchColumn(),
        ];
    }

    private function upsertPage(string $title, string $category, string $type): void
    {
        $itemKey = $this->slug($title);
        $stmt = $this->pdo->prepare(<<<'SQL'
INSERT INTO wiki_catalog_items (
    wiki_title, item_key, source_category, member_type, import_status, updated_at
) VALUES (
    :wiki_title, :item_key, :source_category, :member_type, 'discovered', CURRENT_TIMESTAMP
)
ON CONFLICT(wiki_title) DO UPDATE SET
    item_key = excluded.item_key,
    source_category = excluded.source_category,
    member_type = excluded.member_type,
    updated_at = CURRENT_TIMESTAMP
SQL);
        $stmt->execute([
            ':wiki_title' => $title,
            ':item_key' => $itemKey,
            ':source_category' => $category,
            ':member_type' => $type,
        ]);
    }

    private function upsertCategory(
        string $title,
        ?string $parent,
        int $depth,
        string $status,
        int $pageCount,
        int $subcategoryCount
    ): void {
        $stmt = $this->pdo->prepare(<<<'SQL'
INSERT INTO wiki_catalog_categories (
    category_title, parent_category, depth, import_status,
    page_count, subcategory_count, updated_at
) VALUES (
    :category_title, :parent_category, :depth, :import_status,
    :page_count, :subcategory_count, CURRENT_TIMESTAMP
)
ON CONFLICT(category_title) DO UPDATE SET
    parent_category = COALESCE(excluded.parent_category, wiki_catalog_categories.parent_category),
    depth = MIN(wiki_catalog_categories.depth, excluded.depth),
    import_status = excluded.import_status,
    page_count = excluded.page_count,
    subcategory_count = excluded.subcategory_count,
    updated_at = CURRENT_TIMESTAMP
SQL);
        $stmt->execute([
            ':category_title' => $title,
            ':parent_category' => $parent,
            ':depth' => $depth,
            ':import_status' => $status,
            ':page_count' => $pageCount,
            ':subcategory_count' => $subcategoryCount,
        ]);
    }

    private function markCategoryError(string $title, string $message): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE wiki_catalog_categories
             SET import_status = 'error', updated_at = CURRENT_TIMESTAMP
             WHERE category_title = :title"
        );
        $stmt->execute([':title' => $title]);
    }

    private function slug(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[’\']/u', '', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]+/u', '_', $value) ?? $value;
        return trim($value, '_');
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
        $stmt->execute([':name' => $table]);
        return (bool)$stmt->fetchColumn();
    }
}
