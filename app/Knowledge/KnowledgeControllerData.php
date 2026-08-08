<?php
declare(strict_types=1);

namespace LittyWatch\Knowledge;

final class KnowledgeControllerData
{
    public function __construct(private readonly KnowledgeBase $kb, private readonly \PDO $pdo) {}

    /** @return array<string,int|string> */
    public function importGwMarket(string $category, string $json): array
    {
        return (new GwMarketCatalogImporter($this->pdo))->import($category,$json);
    }

    public function get(): array
    {
        return [
            'stats' => $this->kb->stats(),
            'profiles' => $this->kb->profiles(),
            'attributes' => $this->kb->attributes(),
            'assignments' => $this->kb->profileAssignments(),
        ];
    }
}
