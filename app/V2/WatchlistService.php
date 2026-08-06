<?php

declare(strict_types=1);

namespace LittyWatch\V2;

use PDO;

final class WatchlistService
{
    public function __construct(private PDO $pdo)
    {
        Schema::ensure($this->pdo);
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $sql = <<<'SQL'
SELECT
    w.id,
    w.market_key,
    COALESCE(NULLIF(w.label, ''), mi.item, w.market_key) AS label,
    w.target_buy_ecto,
    w.target_sell_ecto,
    w.created_at,
    w.updated_at,
    mi.item,
    mi.buy_offers,
    mi.sell_offers,
    mi.best_wtb_ecto,
    mi.best_wts_ecto,
    mi.median_wtb_ecto,
    mi.median_wts_ecto,
    mi.last_activity,
    mi.deal_score,
    mi.confidence_score
FROM watchlist w
LEFT JOIN market_intelligence mi ON mi.market_key = w.market_key
ORDER BY
    CASE WHEN mi.last_activity IS NULL THEN 1 ELSE 0 END,
    mi.last_activity DESC,
    w.updated_at DESC,
    w.id DESC
SQL;
        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function marketOptions(string $query = '', int $limit = 250): array
    {
        $limit = max(10, min(500, $limit));
        $query = trim($query);
        $sql = <<<'SQL'
SELECT market_key, item, best_wtb_ecto, best_wts_ecto, last_activity
FROM market_intelligence
WHERE (:query = '' OR item LIKE :like OR market_key LIKE :like)
ORDER BY
    CASE WHEN last_activity IS NULL THEN 1 ELSE 0 END,
    last_activity DESC,
    item COLLATE NOCASE
LIMIT :limit
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':like', '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function save(
        string $marketKey,
        ?string $label = null,
        ?float $targetBuyEcto = null,
        ?float $targetSellEcto = null
    ): void {
        $marketKey = trim($marketKey);
        if ($marketKey === '') {
            throw new \InvalidArgumentException('Kies een marktvariant.');
        }
        if (!$this->marketExists($marketKey)) {
            throw new \InvalidArgumentException('Deze market_key bestaat niet in Market Intelligence.');
        }
        foreach ([$targetBuyEcto, $targetSellEcto] as $target) {
            if ($target !== null && $target <= 0) {
                throw new \InvalidArgumentException('Koersdoelen moeten groter zijn dan 0 ecto.');
            }
        }

        $stmt = $this->pdo->prepare(<<<'SQL'
INSERT INTO watchlist (market_key, label, target_buy_ecto, target_sell_ecto, updated_at)
VALUES (:market_key, :label, :target_buy_ecto, :target_sell_ecto, CURRENT_TIMESTAMP)
ON CONFLICT(market_key) DO UPDATE SET
    label = excluded.label,
    target_buy_ecto = excluded.target_buy_ecto,
    target_sell_ecto = excluded.target_sell_ecto,
    updated_at = CURRENT_TIMESTAMP
SQL);
        $stmt->execute([
            ':market_key' => $marketKey,
            ':label' => ($label !== null && trim($label) !== '') ? trim($label) : null,
            ':target_buy_ecto' => $targetBuyEcto,
            ':target_sell_ecto' => $targetSellEcto,
        ]);
    }

    public function remove(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT market_key FROM watchlist WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $marketKey = $stmt->fetchColumn();
        if ($marketKey === false) {
            return null;
        }
        $this->pdo->prepare('DELETE FROM watchlist WHERE id = :id')->execute([':id' => $id]);
        return (string)$marketKey;
    }

    private function marketExists(string $marketKey): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM market_intelligence WHERE market_key = :key LIMIT 1');
        $stmt->execute([':key' => $marketKey]);
        return (bool)$stmt->fetchColumn();
    }
}
