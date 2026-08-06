<?php
declare(strict_types=1);

namespace LittyWatch\Repositories;

use PDO;

final class WatchlistRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query(<<<'SQL'
SELECT w.id,w.market_key,COALESCE(NULLIF(w.label,''),mi.item,w.market_key) AS label,
       w.target_buy_ecto,w.target_sell_ecto,w.created_at,w.updated_at,
       mi.item,mi.buy_offers,mi.sell_offers,mi.best_wtb_ecto,mi.best_wts_ecto,
       mi.median_wtb_ecto,mi.median_wts_ecto,mi.last_activity,mi.deal_score,mi.confidence_score
FROM watchlist w
LEFT JOIN market_intelligence mi ON mi.market_key=w.market_key
ORDER BY CASE WHEN mi.last_activity IS NULL THEN 1 ELSE 0 END,
         mi.last_activity DESC,w.updated_at DESC,w.id DESC
SQL)->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function marketOptions(string $query='', int $limit=250): array
    {
        $stmt=$this->pdo->prepare(<<<'SQL'
SELECT market_key,item,best_wtb_ecto,best_wts_ecto,last_activity
FROM market_intelligence
WHERE (:query='' OR item LIKE :like OR market_key LIKE :like)
ORDER BY CASE WHEN last_activity IS NULL THEN 1 ELSE 0 END,last_activity DESC,item COLLATE NOCASE
LIMIT :limit
SQL);
        $stmt->bindValue(':query',trim($query));
        $stmt->bindValue(':like','%'.trim($query).'%');
        $stmt->bindValue(':limit',max(10,min(500,$limit)),PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function marketExists(string $marketKey): bool
    {
        $stmt=$this->pdo->prepare('SELECT 1 FROM market_intelligence WHERE market_key=:key LIMIT 1');
        $stmt->execute([':key'=>$marketKey]);
        return (bool)$stmt->fetchColumn();
    }

    public function upsert(string $marketKey, ?string $label, ?float $targetBuy, ?float $targetSell): void
    {
        $stmt=$this->pdo->prepare(<<<'SQL'
INSERT INTO watchlist (market_key,label,target_buy_ecto,target_sell_ecto,updated_at)
VALUES (:market_key,:label,:target_buy,:target_sell,CURRENT_TIMESTAMP)
ON CONFLICT(market_key) DO UPDATE SET
 label=excluded.label,target_buy_ecto=excluded.target_buy_ecto,
 target_sell_ecto=excluded.target_sell_ecto,updated_at=CURRENT_TIMESTAMP
SQL);
        $stmt->execute([':market_key'=>$marketKey,':label'=>$label,':target_buy'=>$targetBuy,':target_sell'=>$targetSell]);
    }

    public function remove(int $id): ?string
    {
        $stmt=$this->pdo->prepare('SELECT market_key FROM watchlist WHERE id=:id');
        $stmt->execute([':id'=>$id]);
        $key=$stmt->fetchColumn();
        if($key===false)return null;
        $this->pdo->prepare('DELETE FROM watchlist WHERE id=:id')->execute([':id'=>$id]);
        return (string)$key;
    }
}
