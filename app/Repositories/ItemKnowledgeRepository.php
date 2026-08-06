<?php
declare(strict_types=1);

namespace LittyWatch\Repositories;

use PDO;

final class ItemKnowledgeRepository
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->install();
        $this->seed();
    }

    public function install(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS item_knowledge (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_name TEXT NOT NULL UNIQUE,
                wiki_title TEXT,
                wiki_url TEXT,
                wiki_extract TEXT,
                rarity TEXT NOT NULL DEFAULT 'unknown',
                item_class TEXT,
                is_unique INTEGER NOT NULL DEFAULT 0,
                fixed_stats INTEGER NOT NULL DEFAULT 0,
                modifiable INTEGER NOT NULL DEFAULT 1,
                canonical_stats_json TEXT,
                source_status TEXT NOT NULL DEFAULT 'manual',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->pdo->exec(
            "CREATE INDEX IF NOT EXISTS idx_item_knowledge_name
             ON item_knowledge(item_name)"
        );
    }

    private function seed(): void
    {
        $statement = $this->pdo->prepare(
            "INSERT OR IGNORE INTO item_knowledge(
                item_name,wiki_title,wiki_url,wiki_extract,rarity,item_class,
                is_unique,fixed_stats,modifiable,canonical_stats_json,source_status
             ) VALUES(?,?,?,?,?,?,?,?,?,?,?)"
        );

        $statement->execute([
            "Madruk's Prophecy",
            "Madruk's Prophecy",
            "https://wiki.guildwars.com/wiki/Madruk%27s_Prophecy",
            "Unique (green) Guild Wars weapon. Unique items use a fixed combination of stats and modifiers.",
            "unique",
            "staff",
            1,
            1,
            0,
            json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "seed",
        ]);
    }

    /** @return array<string,mixed>|null */
    public function find(string $itemName): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM item_knowledge
             WHERE lower(item_name)=lower(?)
             LIMIT 1"
        );
        $statement->execute([trim($itemName)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['canonical_stats'] = json_decode(
            (string)($row['canonical_stats_json'] ?? '[]'),
            true
        ) ?: [];
        return $row;
    }

    /** @return list<array<string,mixed>> */
    public function all(string $query = '', int $limit = 250): array
    {
        if ($query !== '') {
            $statement = $this->pdo->prepare(
                "SELECT * FROM item_knowledge
                 WHERE item_name LIKE :query OR wiki_title LIKE :query
                 ORDER BY item_name
                 LIMIT :limit"
            );
            $statement->bindValue(':query', '%' . $query . '%');
            $statement->bindValue(':limit', max(1, min(1000, $limit)), PDO::PARAM_INT);
            $statement->execute();
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        $statement = $this->pdo->prepare(
            "SELECT * FROM item_knowledge
             ORDER BY item_name
             LIMIT ?"
        );
        $statement->bindValue(1, max(1, min(1000, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string,mixed> $data */
    public function save(array $data): void
    {
        $itemName = trim((string)($data['item_name'] ?? ''));
        if ($itemName === '') {
            throw new \RuntimeException('Itemnaam ontbreekt.');
        }

        $statsText = trim((string)($data['canonical_stats'] ?? ''));
        $stats = [];
        if ($statsText !== '') {
            foreach (preg_split('/\r?\n/u', $statsText) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') $stats[] = $line;
            }
        }

        $statement = $this->pdo->prepare(
            "INSERT INTO item_knowledge(
                item_name,wiki_title,wiki_url,wiki_extract,rarity,item_class,
                is_unique,fixed_stats,modifiable,canonical_stats_json,
                source_status,updated_at
             ) VALUES(
                :item_name,:wiki_title,:wiki_url,:wiki_extract,:rarity,:item_class,
                :is_unique,:fixed_stats,:modifiable,:canonical_stats_json,
                :source_status,CURRENT_TIMESTAMP
             )
             ON CONFLICT(item_name) DO UPDATE SET
                wiki_title=excluded.wiki_title,
                wiki_url=excluded.wiki_url,
                wiki_extract=excluded.wiki_extract,
                rarity=excluded.rarity,
                item_class=excluded.item_class,
                is_unique=excluded.is_unique,
                fixed_stats=excluded.fixed_stats,
                modifiable=excluded.modifiable,
                canonical_stats_json=excluded.canonical_stats_json,
                source_status=excluded.source_status,
                updated_at=CURRENT_TIMESTAMP"
        );

        $statement->execute([
            ':item_name' => $itemName,
            ':wiki_title' => trim((string)($data['wiki_title'] ?? '')),
            ':wiki_url' => trim((string)($data['wiki_url'] ?? '')),
            ':wiki_extract' => trim((string)($data['wiki_extract'] ?? '')),
            ':rarity' => trim((string)($data['rarity'] ?? 'unknown')) ?: 'unknown',
            ':item_class' => trim((string)($data['item_class'] ?? '')),
            ':is_unique' => !empty($data['is_unique']) ? 1 : 0,
            ':fixed_stats' => !empty($data['fixed_stats']) ? 1 : 0,
            ':modifiable' => !empty($data['modifiable']) ? 1 : 0,
            ':canonical_stats_json' => json_encode(
                $stats,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            ':source_status' => trim((string)($data['source_status'] ?? 'manual')) ?: 'manual',
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare("DELETE FROM item_knowledge WHERE id=?");
        $statement->execute([$id]);
    }
}
