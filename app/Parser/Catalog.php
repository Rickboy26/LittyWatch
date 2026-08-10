<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

use RuntimeException;

final class Catalog
{
    private array $items;
    private array $modifiers;
    private array $rejectPatterns;
    private array $taxonomy = [];
    private ?\LittyWatch\Knowledge\KnowledgeBase $knowledgeBase = null;
    private ?\PDO $database = null;

    public function __construct(string $dataDir, ?\PDO $db = null)
    {
        $this->items = $this->loadJson($dataDir . '/items.json');
        $packItemsPath = $dataDir . '/knowledge-pack/items.json';
        $packAliasesPath = $dataDir . '/knowledge-pack/aliases.json';
        if (is_file($packItemsPath)) {
            $this->items = $this->mergeItems($this->items, $this->loadJson($packItemsPath));
        }
        if (is_file($packAliasesPath)) {
            $this->items = $this->mergeAliases($this->items, $this->loadJson($packAliasesPath));
        }
        // LITTYWATCH_PHASE4D_CATALOG_RECONCILIATION
        $phase4dItemsPath = $dataDir . '/phase4d-items.json';
        if (is_file($phase4dItemsPath)) {
            $this->items = $this->mergeItems($this->items, $this->loadJson($phase4dItemsPath));
        }

        // LITTYWATCH_PHASE4E_MARKET_IDENTITIES
        $phase4eItemsPath = $dataDir . '/phase4e-items.json';
        if (is_file($phase4eItemsPath)) {
            $this->items = $this->mergeItems($this->items, $this->loadJson($phase4eItemsPath));
        }
        // LITTYWATCH_PHASE4F_RESIDUAL_CATALOG
        $phase4fItemsPath = $dataDir . '/phase4f-items.json';
        if (is_file($phase4fItemsPath)) {
            $this->items = $this->mergeItems($this->items, $this->loadJson($phase4fItemsPath));
        }
        // LITTYWATCH_PHASE4H_FINAL_CATALOG
        $phase4hItemsPath = $dataDir . '/phase4h-items.json';
        if (is_file($phase4hItemsPath)) {
            $this->items = $this->mergeItems($this->items, $this->loadJson($phase4hItemsPath));
        }
        $this->modifiers = $this->loadJson($dataDir . '/modifiers.json');
        $this->rejectPatterns = $this->loadJson($dataDir . '/reject-patterns.json');
        $taxonomyPath = $dataDir . '/taxonomy.json';
        $this->taxonomy = is_file($taxonomyPath) ? $this->loadJson($taxonomyPath) : [];
        if ($db !== null) {
            $this->database = $db;
            \LittyWatch\Knowledge\Schema::install($db);
            $this->knowledgeBase = new \LittyWatch\Knowledge\KnowledgeBase($db);
            // LITTYWATCH_PHASE4D1_KB_CATALOG_SYNC
            // CatalogFirstResolver and StrictCatalogGate read kb_items/kb_aliases
            // directly. Keep those tables aligned with the merged parser catalog.
            $syncByName = [];
            foreach ($this->items as $catalogItem) {
                if (!is_array($catalogItem)) continue;
                $catalogKey = trim((string)($catalogItem['key'] ?? ''));
                $rawName = trim((string)($catalogItem['name'] ?? ''));
                if ($catalogKey === '' || $rawName === '') continue;
                $canonicalName = \LittyWatch\Market\CanonicalMarketIdentity::nameFor($rawName, $catalogKey);
                $nameNorm = \LittyWatch\Knowledge\KnowledgeBase::normalize($canonicalName);
                if ($nameNorm === '') continue;
                $aliases = array_values(array_unique(array_filter(array_map(
                    static fn(mixed $a): string => trim((string)$a),
                    array_merge([$rawName, $canonicalName], $catalogItem['aliases'] ?? [])
                ))));
                if (!isset($syncByName[$nameNorm])) {
                    $syncByName[$nameNorm] = [
                        'key'=>$catalogKey,
                        'name'=>$canonicalName,
                        'category'=>(string)($catalogItem['category'] ?? 'unknown'),
                        'aliases'=>$aliases,
                    ];
                } else {
                    $syncByName[$nameNorm]['aliases'] = array_values(array_unique(array_merge(
                        $syncByName[$nameNorm]['aliases'], $aliases
                    )));
                }
            }
            $syncItem = $db->prepare("INSERT INTO kb_items(key,name,category_key,source,source_id,metadata_json,active,updated_at) VALUES(:key,:name,:category,'parser_catalog',NULL,'{}',1,:updated) ON CONFLICT(key) DO UPDATE SET name=excluded.name, category_key=CASE WHEN kb_items.category_key='' OR kb_items.category_key='unknown' THEN excluded.category_key ELSE kb_items.category_key END, active=1, updated_at=excluded.updated_at");
            $syncAlias = $db->prepare("INSERT OR IGNORE INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES(:item_key,:alias,:normalized,'parser_catalog')");
            foreach ($syncByName as $syncItemRow) {
                $syncItem->execute([
                    ':key'=>$syncItemRow['key'], ':name'=>$syncItemRow['name'],
                    ':category'=>$syncItemRow['category'], ':updated'=>gmdate('c'),
                ]);
                foreach ($syncItemRow['aliases'] as $syncAliasValue) {
                    $normalizedAlias = \LittyWatch\Knowledge\KnowledgeBase::normalize((string)$syncAliasValue);
                    if ($normalizedAlias === '') continue;
                    $syncAlias->execute([
                        ':item_key'=>$syncItemRow['key'], ':alias'=>(string)$syncAliasValue,
                        ':normalized'=>$normalizedAlias,
                    ]);
                }
            }
            $dbItems = $this->knowledgeBase->allItems();
            if ($dbItems !== []) {
                $mapped = array_map(
                    static fn(array $i): array => [
                        'key'=>$i['key'],
                        'name'=>$i['name'],
                        'category'=>$i['category_key'],
                        'aliases'=>$i['aliases']
                    ],
                    $dbItems
                );
                $this->items = $this->mergeItems($this->items, $mapped);
            }
        }
    }

    public function items(): array { return $this->items; }
    public function modifiers(): array { return $this->modifiers; }
    public function rejectPatterns(): array { return $this->rejectPatterns; }
    public function taxonomy(): array { return $this->taxonomy; }
    public function knowledgeBase(): ?\LittyWatch\Knowledge\KnowledgeBase { return $this->knowledgeBase; }
    public function database(): ?\PDO { return $this->database; }

    /** @param list<array<string,mixed>> $base @param list<array<string,mixed>> $extra */
    private function mergeItems(array $base, array $extra): array
    {
        $indexed = [];
        foreach (array_merge($base, $extra) as $item) {
            if (!is_array($item)) continue;
            $name = trim((string)($item['name'] ?? ''));
            if ($name === '') continue;
            $key = trim((string)($item['key'] ?? '')) ?: $this->key($name);
            $aliases = array_values(array_unique(array_filter(array_map(
                static fn(mixed $alias): string => trim((string)$alias),
                array_merge([$name], $item['aliases'] ?? [])
            ))));

            if (isset($indexed[$key])) {
                $aliases = array_values(array_unique(array_merge($indexed[$key]['aliases'], $aliases)));
            }

            $indexed[$key] = [
                'key'=>$key,
                'name'=>$name,
                'category'=>(string)($item['category'] ?? ($indexed[$key]['category'] ?? 'unknown')),
                'aliases'=>$aliases,
                // Phase 3G: parser-owned market semantics may be declared in the
                // catalog. Preserve them across knowledge-pack/database merges.
                'market_price_basis'=>(string)($item['market_price_basis'] ?? ($indexed[$key]['market_price_basis'] ?? '')),
                'market_stack_size'=>(int)($item['market_stack_size'] ?? ($indexed[$key]['market_stack_size'] ?? 0)),
                'market_quote_basis'=>(string)($item['market_quote_basis'] ?? ($indexed[$key]['market_quote_basis'] ?? $item['market_price_basis'] ?? ($indexed[$key]['market_price_basis'] ?? ''))),
                'market_quote_size'=>(int)($item['market_quote_size'] ?? ($indexed[$key]['market_quote_size'] ?? $item['market_stack_size'] ?? ($indexed[$key]['market_stack_size'] ?? 0))),
                'market_display_basis'=>(string)($item['market_display_basis'] ?? ($indexed[$key]['market_display_basis'] ?? 'each')),
            ];
        }
        return array_values($indexed);
    }

    /** @param list<array<string,mixed>> $items @param list<array<string,mixed>> $aliases */
    private function mergeAliases(array $items, array $aliases): array
    {
        $byName = [];
        foreach ($items as $index=>$item) {
            $byName[strtolower((string)$item['name'])] = $index;
        }

        foreach ($aliases as $row) {
            if (!is_array($row)) continue;
            $alias = trim((string)($row['alias'] ?? ''));
            $name = trim((string)($row['item'] ?? ''));
            $index = $byName[strtolower($name)] ?? null;
            if ($alias === '' || $index === null) continue;
            $items[$index]['aliases'] = array_values(array_unique(array_merge(
                $items[$index]['aliases'] ?? [],
                [$alias]
            )));
        }

        return $items;
    }

    private function key(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u','-', $value) ?? $value;
        return trim($value,'-');
    }

    private function loadJson(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Ontbrekend parser-databestand: ' . $path);
        }
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Ongeldige parserdata: ' . $path);
        }
        return $decoded;
    }
}
