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
            'offers' => (int)$this->pdo->query('SELECT COUNT(*) FROM structured_offers')->fetchColumn(),
            'accepted' => (int)$this->pdo->query("SELECT COUNT(*) FROM structured_offers WHERE quality_status='accepted'")->fetchColumn(),
            'review' => (int)$this->pdo->query("SELECT COUNT(*) FROM structured_offers WHERE quality_status='review'")->fetchColumn(),
            'buy' => (int)$this->pdo->query("SELECT COUNT(*) FROM structured_offers WHERE trade_type='buy'")->fetchColumn(),
            'sell' => (int)$this->pdo->query("SELECT COUNT(*) FROM structured_offers WHERE trade_type='sell'")->fetchColumn(),
            'latest_posted_at' => $this->pdo->query('SELECT MAX(posted_at) FROM messages')->fetchColumn() ?: null,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function latestOffers(string $query = '', string $type = '', string $status = '', int $limit = 150): array
    {
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(o.item LIKE :q OR o.raw_segment LIKE :q OR m.player LIKE :q OR m.message LIKE :q)';
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

        $sql = 'SELECT o.*,'.$this->variantExpr('o').' AS details,m.player,m.message,m.posted_at FROM structured_offers o JOIN messages m ON m.id=o.message_id'
            . ($where ? ' WHERE '.implode(' AND ', $where) : '')
            . ' ORDER BY datetime(m.posted_at) DESC,o.id DESC LIMIT '.max(1, min(500, $limit));
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function itemDirectory(string $query = '', int $limit = 250): array
    {
        $where = "WHERE o.quality_status='accepted' AND COALESCE(o.lifecycle_status,'active')='active' AND o.item <> '' AND o.item_key <> '' AND o.item NOT LIKE 'Bundle:%'";
        $params = [];
        if ($query !== '') {
            $where .= ' AND (o.item LIKE :q OR o.raw_segment LIKE :q OR o.market_key LIKE :q OR o.normalized_market_key LIKE :q)';
            $params[':q'] = '%'.$query.'%';
        }

        // Phase 3A: Items is a canonical directory. Group by structured item_key,
        // not the legacy free-text item label. This collapses casing/alias variants
        // and prevents legacy fallback fragments from becoming item pages.
        $sql = "SELECT MIN(o.item) AS item,
                       o.item_key,
                       COUNT(*) AS offers,
                       SUM(CASE WHEN o.trade_type='buy' THEN 1 ELSE 0 END) AS buy_count,
                       SUM(CASE WHEN o.trade_type='sell' THEN 1 ELSE 0 END) AS sell_count,
                       ROUND(AVG(CASE WHEN o.trade_type='buy' AND ".$this->trustedPriceExpr('o')." THEN o.unit_price_ecto END), 2) AS avg_buy,
                       ROUND(AVG(CASE WHEN o.trade_type='sell' AND ".$this->trustedPriceExpr('o')." THEN o.unit_price_ecto END), 2) AS avg_sell,
                       MAX(m.posted_at) AS latest_posted_at
                FROM structured_offers o
                JOIN messages m ON m.id=o.message_id
                $where
                GROUP BY lower(o.item)
                ORDER BY offers DESC, item ASC
                LIMIT ".max(1, min(1000, $limit));
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function itemSummary(string $name): ?array
    {
        $key = $this->itemKeyForName($name);
        if ($key === null) return null;

        $statement = $this->pdo->prepare("SELECT MIN(o.item) AS item,
                       o.item_key,
                       COUNT(*) AS offers,
                       SUM(CASE WHEN o.trade_type='buy' THEN 1 ELSE 0 END) AS buy_count,
                       SUM(CASE WHEN o.trade_type='sell' THEN 1 ELSE 0 END) AS sell_count,
                       SUM(CASE WHEN o.quality_status='review' THEN 1 ELSE 0 END) AS review_count,
                       MIN(CASE WHEN o.trade_type='sell' AND ".$this->trustedPriceExpr('o')." THEN o.unit_price_ecto END) AS lowest_sell,
                       MAX(CASE WHEN o.trade_type='buy' AND ".$this->trustedPriceExpr('o')." THEN o.unit_price_ecto END) AS highest_buy,
                       ROUND(AVG(CASE WHEN o.trade_type='sell' AND ".$this->trustedPriceExpr('o')." THEN o.unit_price_ecto END), 2) AS avg_sell,
                       ROUND(AVG(CASE WHEN o.trade_type='buy' AND ".$this->trustedPriceExpr('o')." THEN o.unit_price_ecto END), 2) AS avg_buy,
                       MAX(m.posted_at) AS latest_posted_at
                FROM structured_offers o
                JOIN messages m ON m.id=o.message_id
                WHERE lower(o.item) = lower(:item)
                  AND o.quality_status='accepted'
                  AND COALESCE(o.lifecycle_status,'active')='active'
                GROUP BY lower(o.item)");
        $statement->execute([':item' => $name]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function offersForItem(string $name, int $limit = 200): array
    {
        $key = $this->itemKeyForName($name);
        if ($key === null) return [];
        $statement = $this->pdo->prepare("SELECT o.*,".$this->variantExpr('o')." AS details,m.player,m.message,m.posted_at
            FROM structured_offers o JOIN messages m ON m.id=o.message_id
            WHERE lower(o.item)=lower(:item) AND o.quality_status='accepted' AND COALESCE(o.lifecycle_status,'active')='active'
            ORDER BY datetime(m.posted_at) DESC,o.id DESC LIMIT ".max(1,min(500,$limit)));
        $statement->execute([':item'=>$name]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function variantsForItem(string $name): array
    {
        $key = $this->itemKeyForName($name);
        if ($key === null) return [];
        $variant = $this->variantExpr('o');
        $statement = $this->pdo->prepare("SELECT $variant AS variant,
                       COUNT(*) AS offers,
                       SUM(CASE WHEN o.trade_type='buy' THEN 1 ELSE 0 END) AS buy_count,
                       SUM(CASE WHEN o.trade_type='sell' THEN 1 ELSE 0 END) AS sell_count,
                       ROUND(AVG(CASE WHEN o.trade_type='sell' AND ".$this->trustedPriceExpr('o')." THEN o.unit_price_ecto END),2) AS avg_sell,
                       ROUND(AVG(CASE WHEN o.trade_type='buy' AND ".$this->trustedPriceExpr('o')." THEN o.unit_price_ecto END),2) AS avg_buy
                FROM structured_offers o
                WHERE lower(o.item)=lower(:item) AND o.quality_status='accepted' AND COALESCE(o.lifecycle_status,'active')='active'
                GROUP BY $variant
                ORDER BY offers DESC
                LIMIT 50");
        $statement->execute([':item'=>$name]);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed> */
    public function itemAnalytics(string $name, string $scope = '100', string $variant = ''): array
    {
        $limit = match ($scope) {'30' => 30, 'all' => 10000, default => 100};
        $key = $this->itemKeyForName($name);
        if ($key === null) return $this->emptyAnalytics($scope, $variant);

        $variantExpr = $this->variantExpr('o');
        $where = "lower(o.item)=lower(:item) AND o.quality_status='accepted' AND COALESCE(o.lifecycle_status,'active')='active' AND ".$this->trustedPriceExpr('o');
        $params = [':item' => $name];
        if ($variant !== '') {
            $where .= " AND $variantExpr=:variant";
            $params[':variant'] = $variant;
        }
        $sql = "SELECT o.trade_type,o.unit_price_ecto,$variantExpr AS details,m.player,m.posted_at,o.id
                FROM structured_offers o JOIN messages m ON m.id=o.message_id
                WHERE $where ORDER BY o.id DESC LIMIT ".$limit;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = array_reverse($statement->fetchAll());

        $buy=[];$sell=[];$traders=[];$points=[];
        foreach ($rows as $row) {
            $price=(float)$row['unit_price_ecto']; if ($price<=0) continue;
            $type=(string)$row['trade_type']; if($type==='buy')$buy[]=$price; if($type==='sell')$sell[]=$price;
            $traders[(string)$row['player']]=true;
            $points[]=['type'=>$type,'price'=>$price,'player'=>(string)$row['player'],'posted_at'=>(string)$row['posted_at'],'id'=>(int)$row['id']];
        }
        $median=static function(array $values):?float{if(!$values)return null;sort($values,SORT_NUMERIC);$count=count($values);$middle=intdiv($count,2);return$count%2?(float)$values[$middle]:((float)$values[$middle-1]+(float)$values[$middle])/2;};
        $buyMedian=$median($buy);$sellMedian=$median($sell);
        return ['scope'=>$scope,'variant'=>$variant,'points'=>$points,'buy_count'=>count($buy),'sell_count'=>count($sell),'unique_traders'=>count($traders),'buy_median'=>$buyMedian,'sell_median'=>$sellMedian,'spread'=>($buyMedian!==null&&$sellMedian!==null)?$buyMedian-$sellMedian:null,'buy_min'=>$buy?min($buy):null,'buy_max'=>$buy?max($buy):null,'sell_min'=>$sell?min($sell):null,'sell_max'=>$sell?max($sell):null];
    }

    /** @return list<array<string,mixed>> */
    public function activeOffersForItem(string $name, string $type, int $limit = 30): array
    {
        if (!in_array($type, ['buy','sell'], true)) return [];
        $key=$this->itemKeyForName($name); if($key===null)return[];
        $order=$type==='buy'?'o.unit_price_ecto DESC':'o.unit_price_ecto ASC';
        $statement=$this->pdo->prepare("SELECT o.*,".$this->variantExpr('o')." AS details,m.player,m.message,m.posted_at
             FROM structured_offers o JOIN messages m ON m.id=o.message_id
             WHERE lower(o.item)=lower(:item) AND o.trade_type=:type AND o.quality_status='accepted' AND COALESCE(o.lifecycle_status,'active')='active'
             ORDER BY CASE WHEN o.unit_price_ecto IS NULL THEN 1 ELSE 0 END,$order,datetime(m.posted_at) DESC,o.id DESC
             LIMIT ".max(1,min(100,$limit)));
        $statement->execute([':item'=>$name,':type'=>$type]); return$statement->fetchAll();
    }

    /** @return array{buy:?array,sell:?array} */
    public function bestOffersForItem(string $name): array
    {
        $key=$this->itemKeyForName($name); if($key===null)return['buy'=>null,'sell'=>null];
        $best=[];
        foreach(['buy','sell'] as $type){
            $order=$type==='buy'?'o.unit_price_ecto DESC':'o.unit_price_ecto ASC';
            $statement=$this->pdo->prepare("SELECT o.*,".$this->variantExpr('o')." AS details,m.player,m.message,m.posted_at
                FROM structured_offers o JOIN messages m ON m.id=o.message_id
                WHERE lower(o.item)=lower(:item) AND o.trade_type=:type AND o.quality_status='accepted'
                  AND COALESCE(o.lifecycle_status,'active')='active' AND ".$this->trustedPriceExpr('o')."
                ORDER BY $order,datetime(m.posted_at) DESC,o.id DESC LIMIT 1");
            $statement->execute([':item'=>$name,':type'=>$type]);
            $row=$statement->fetch(); $best[$type]=$row?:null;
        }
        return $best;
    }

    public function canonicalItemName(string $name): ?string
    {
        $statement=$this->pdo->prepare("SELECT item FROM structured_offers WHERE lower(item)=lower(:item) AND quality_status='accepted' GROUP BY item ORDER BY COUNT(*) DESC,LENGTH(item) DESC LIMIT 1");
        $statement->execute([':item'=>trim($name)]);
        $value=$statement->fetchColumn();
        return is_string($value)&&$value!==''?$value:null;
    }

    private function itemKeyForName(string $name): ?string
    {
        $statement=$this->pdo->prepare("SELECT item_key FROM structured_offers WHERE quality_status='accepted' AND (lower(item)=lower(:item) OR item_key=:raw_key) GROUP BY item_key ORDER BY COUNT(*) DESC LIMIT 1");
        $statement->execute([':item'=>trim($name),':raw_key'=>$this->key(trim($name))]);
        $value=$statement->fetchColumn(); return is_string($value)&&$value!==''?$value:null;
    }

    private function trustedPriceExpr(string $alias): string
    {
        // Phase 3B: only explicit money observations contribute to price stats.
        // Quantity-only, bundle and exchange observations stay visible as offers
        // but cannot distort the item averages/medians.
        return "$alias.unit_price_ecto IS NOT NULL AND $alias.unit_price_ecto > 0 AND COALESCE($alias.price_currency,'') IN ('a','e','k') AND COALESCE($alias.price_basis,'') NOT IN ('bundle','currency_exchange','unknown') AND COALESCE($alias.price_basis,'') NOT IN ('currency_conversion','unqualified','uncertain')";
    }

    private function variantExpr(string $alias): string
    {
        return "CASE WHEN $alias.requirement IS NULL AND COALESCE($alias.attribute_name,'')='' AND COALESCE($alias.is_oldschool,0)=0 AND COALESCE($alias.is_inscribable,0)=0 THEN 'Standaard' ELSE trim(COALESCE(CASE WHEN $alias.requirement IS NOT NULL THEN 'q'||$alias.requirement||' ' END,'')||COALESCE($alias.attribute_name||' ','')||CASE WHEN COALESCE($alias.is_oldschool,0)=1 THEN 'OS ' ELSE '' END||CASE WHEN COALESCE($alias.is_inscribable,0)=1 THEN 'inscribable' ELSE '' END) END";
    }

    private function key(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/',' ',mb_strtolower($value))??'');
    }

    /** @return array<string,mixed> */
    private function emptyAnalytics(string $scope,string $variant):array
    {
        return['scope'=>$scope,'variant'=>$variant,'points'=>[],'buy_count'=>0,'sell_count'=>0,'unique_traders'=>0,'buy_median'=>null,'sell_median'=>null,'spread'=>null,'buy_min'=>null,'buy_max'=>null,'sell_min'=>null,'sell_max'=>null];
    }
}
