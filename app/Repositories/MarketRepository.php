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
                WHERE o.item = :item AND o.quality_status='accepted'
                GROUP BY o.item");
        $statement->execute([':item' => $name]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function offersForItem(string $name, int $limit = 200): array
    {
        $statement = $this->pdo->prepare("SELECT o.*,m.player,m.message,m.posted_at FROM offers o JOIN messages m ON m.id=o.message_id WHERE o.item=:item AND o.quality_status='accepted' ORDER BY datetime(m.posted_at) DESC,o.id DESC LIMIT ".max(1,min(500,$limit)));
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
                WHERE item=:item AND quality_status='accepted'
                GROUP BY COALESCE(NULLIF(details,''),'Standaard')
                ORDER BY offers DESC
                LIMIT 50");
        $statement->execute([':item'=>$name]);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed> */
    public function itemAnalytics(string $name, string $scope = '100', string $variant = ''): array
    {
        $limit = match ($scope) {
            '30' => 30,
            'all' => 10000,
            default => 100,
        };

        $where = "o.item=:item AND o.quality_status='accepted' AND o.unit_price_ecto IS NOT NULL AND COALESCE(o.price_basis,'') NOT IN ('bundle','currency_exchange')";
        $params = [':item' => $name];
        if ($variant !== '') {
            $where .= " AND COALESCE(NULLIF(o.details,''),'Standaard')=:variant";
            $params[':variant'] = $variant;
        }

        $sql = "SELECT o.trade_type,o.unit_price_ecto,o.details,m.player,m.posted_at,o.id
                FROM offers o JOIN messages m ON m.id=o.message_id
                WHERE $where
                ORDER BY o.id DESC LIMIT ".$limit;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = array_reverse($statement->fetchAll());

        $buy = [];
        $sell = [];
        $traders = [];
        $points = [];
        foreach ($rows as $row) {
            $price = (float)$row['unit_price_ecto'];
            if ($price <= 0) continue;
            $type = (string)$row['trade_type'];
            if ($type === 'buy') $buy[] = $price;
            if ($type === 'sell') $sell[] = $price;
            $traders[(string)$row['player']] = true;
            $points[] = [
                'type' => $type,
                'price' => $price,
                'player' => (string)$row['player'],
                'posted_at' => (string)$row['posted_at'],
                'id' => (int)$row['id'],
            ];
        }

        $median = static function(array $values): ?float {
            if (!$values) return null;
            sort($values, SORT_NUMERIC);
            $count = count($values);
            $middle = intdiv($count, 2);
            return $count % 2 ? (float)$values[$middle] : ((float)$values[$middle - 1] + (float)$values[$middle]) / 2;
        };

        $buyMedian = $median($buy);
        $sellMedian = $median($sell);
        return [
            'scope' => $scope,
            'variant' => $variant,
            'points' => $points,
            'buy_count' => count($buy),
            'sell_count' => count($sell),
            'unique_traders' => count($traders),
            'buy_median' => $buyMedian,
            'sell_median' => $sellMedian,
            'spread' => ($buyMedian !== null && $sellMedian !== null) ? $buyMedian - $sellMedian : null,
            'buy_min' => $buy ? min($buy) : null,
            'buy_max' => $buy ? max($buy) : null,
            'sell_min' => $sell ? min($sell) : null,
            'sell_max' => $sell ? max($sell) : null,
        ];
    }


}
