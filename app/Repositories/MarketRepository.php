<?php
declare(strict_types=1);

namespace LittyWatch\Repositories;

use PDO;

final class MarketRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,int|string|null> */
    public function counters(): array
    {
        return [
            'messages' => (int)$this->pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
            'offers' => (int)$this->pdo->query('SELECT COUNT(*) FROM offers')->fetchColumn(),
            'accepted' => (int)$this->pdo->query("SELECT COUNT(*) FROM offers WHERE quality_status='accepted'")->fetchColumn(),
            'review' => (int)$this->pdo->query("SELECT COUNT(*) FROM offers WHERE quality_status='review'")->fetchColumn(),
            'buy' => (int)$this->pdo->query("SELECT COUNT(*) FROM offers WHERE trade_type='buy'")->fetchColumn(),
            'sell' => (int)$this->pdo->query("SELECT COUNT(*) FROM offers WHERE trade_type='sell'")->fetchColumn(),
            'latest_posted_at' => $this->pdo->query('SELECT MAX(posted_at) FROM messages')->fetchColumn() ?: null,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function latestOffers(string $query = '', string $type = '', string $status = '', int $limit = 150): array
    {
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(o.item LIKE :q OR o.details LIKE :q OR m.player LIKE :q OR m.message LIKE :q OR o.raw_segment LIKE :q)';
            $params[':q'] = '%'.$query.'%';
        }
        if (in_array($type, ['buy','sell','trade'], true)) {
            $where[] = 'o.trade_type = :type';
            $params[':type'] = $type;
        }
        if (in_array($status, ['accepted','review'], true)) {
            $where[] = 'o.quality_status = :status';
            $params[':status'] = $status;
        }

        $sql = 'SELECT o.*,m.player,m.message,m.posted_at FROM offers o JOIN messages m ON m.id=o.message_id'
            . ($where ? ' WHERE '.implode(' AND ', $where) : '')
            . ' ORDER BY datetime(m.posted_at) DESC,o.id DESC LIMIT '.max(1, min(500, $limit));
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function itemDirectory(string $query = '', int $limit = 250): array
    {
        $where = "WHERE o.quality_status='accepted' AND o.item <> ''";
        $params = [];
        if ($query !== '') {
            $where .= ' AND (o.item LIKE :q OR o.details LIKE :q)';
            $params[':q'] = '%'.$query.'%';
        }

        $sql = "SELECT o.item,
                       COUNT(*) AS offers,
                       SUM(CASE WHEN o.trade_type='buy' THEN 1 ELSE 0 END) AS buy_count,
                       SUM(CASE WHEN o.trade_type='sell' THEN 1 ELSE 0 END) AS sell_count,
                       ROUND(AVG(CASE WHEN o.trade_type='buy' AND o.unit_price_ecto IS NOT NULL THEN o.unit_price_ecto END), 2) AS avg_buy,
                       ROUND(AVG(CASE WHEN o.trade_type='sell' AND o.unit_price_ecto IS NOT NULL THEN o.unit_price_ecto END), 2) AS avg_sell,
                       MAX(m.posted_at) AS latest_posted_at
                FROM offers o
                JOIN messages m ON m.id=o.message_id
                $where
                GROUP BY o.item
                ORDER BY offers DESC, o.item ASC
                LIMIT ".max(1, min(1000, $limit));
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function itemSummary(string $name): ?array
    {
        $statement = $this->pdo->prepare("SELECT o.item,
                       COUNT(*) AS offers,
                       SUM(CASE WHEN o.trade_type='buy' THEN 1 ELSE 0 END) AS buy_count,
                       SUM(CASE WHEN o.trade_type='sell' THEN 1 ELSE 0 END) AS sell_count,
                       SUM(CASE WHEN o.quality_status='review' THEN 1 ELSE 0 END) AS review_count,
                       MIN(CASE WHEN o.trade_type='sell' AND o.unit_price_ecto IS NOT NULL THEN o.unit_price_ecto END) AS lowest_sell,
                       MAX(CASE WHEN o.trade_type='buy' AND o.unit_price_ecto IS NOT NULL THEN o.unit_price_ecto END) AS highest_buy,
                       ROUND(AVG(CASE WHEN o.trade_type='sell' AND o.unit_price_ecto IS NOT NULL THEN o.unit_price_ecto END), 2) AS avg_sell,
                       ROUND(AVG(CASE WHEN o.trade_type='buy' AND o.unit_price_ecto IS NOT NULL THEN o.unit_price_ecto END), 2) AS avg_buy,
                       MAX(m.posted_at) AS latest_posted_at
                FROM offers o
                JOIN messages m ON m.id=o.message_id
                WHERE o.item = :item
                GROUP BY o.item");
        $statement->execute([':item' => $name]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function offersForItem(string $name, int $limit = 200): array
    {
        $statement = $this->pdo->prepare('SELECT o.*,m.player,m.message,m.posted_at FROM offers o JOIN messages m ON m.id=o.message_id WHERE o.item=:item ORDER BY datetime(m.posted_at) DESC,o.id DESC LIMIT '.max(1,min(500,$limit)));
        $statement->execute([':item'=>$name]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function variantsForItem(string $name): array
    {
        $statement = $this->pdo->prepare("SELECT COALESCE(NULLIF(details,''),'Standaard') AS variant,
                       COUNT(*) AS offers,
                       SUM(CASE WHEN trade_type='buy' THEN 1 ELSE 0 END) AS buy_count,
                       SUM(CASE WHEN trade_type='sell' THEN 1 ELSE 0 END) AS sell_count,
                       ROUND(AVG(CASE WHEN trade_type='sell' AND unit_price_ecto IS NOT NULL THEN unit_price_ecto END),2) AS avg_sell,
                       ROUND(AVG(CASE WHEN trade_type='buy' AND unit_price_ecto IS NOT NULL THEN unit_price_ecto END),2) AS avg_buy
                FROM offers
                WHERE item=:item
                GROUP BY COALESCE(NULLIF(details,''),'Standaard')
                ORDER BY offers DESC
                LIMIT 50");
        $statement->execute([':item'=>$name]);
        return $statement->fetchAll();
    }

}
