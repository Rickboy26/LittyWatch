<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

use RuntimeException;

final class Catalog
{
    private array $items;
    private array $modifiers;
    private array $rejectPatterns;
    private ?\LittyWatch\Knowledge\KnowledgeBase $knowledgeBase = null;
    private ?\PDO $database = null;

    public function __construct(string $dataDir, ?\PDO $db = null)
    {
        $this->items = $this->loadJson($dataDir . '/items.json');
        $this->modifiers = $this->loadJson($dataDir . '/modifiers.json');
        $this->rejectPatterns = $this->loadJson($dataDir . '/reject-patterns.json');
        if ($db !== null) {
            $this->database = $db;
            \LittyWatch\Knowledge\Schema::install($db);
            $this->knowledgeBase = new \LittyWatch\Knowledge\KnowledgeBase($db);
            $dbItems = $this->knowledgeBase->allItems();
            if ($dbItems !== []) {
                $this->items = array_map(static fn(array $i): array => ['key'=>$i['key'],'name'=>$i['name'],'category'=>$i['category_key'],'aliases'=>$i['aliases']], $dbItems);
            }
        }
    }

    public function items(): array { return $this->items; }
    public function modifiers(): array { return $this->modifiers; }
    public function rejectPatterns(): array { return $this->rejectPatterns; }
    public function knowledgeBase(): ?\LittyWatch\Knowledge\KnowledgeBase { return $this->knowledgeBase; }
    public function database(): ?\PDO { return $this->database; }

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
