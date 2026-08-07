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
                       SUM(CASE WHEN o.trade_type='buy' AND ".$this->trustedPriceExpr('o')." THEN 1 ELSE 0 END) AS buy_usable_count,
                       SUM(CASE WHEN o.trade_type='sell' AND ".$this->trustedPriceExpr('o')." THEN 1 ELSE 0 END) AS sell_usable_count,
                       SUM(CASE WHEN o.trade_type='buy' AND COALESCE(o.price_quality_status,'trusted') IN ('uncertain','outlier') THEN 1 ELSE 0 END) AS buy_uncertain_count,
                       SUM(CASE WHEN o.trade_type='sell' AND COALESCE(o.price_quality_status,'trusted') IN ('uncertain','outlier') THEN 1 ELSE 0 END) AS sell_uncertain_count,
                       SUM(CASE WHEN COALESCE(o.price_quality_status,'trusted') IN ('uncertain','outlier') THEN 1 ELSE 0 END) AS review_count,
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
        return $this->sanitizeDisplayedPrices($statement->fetchAll());
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
        $statement->execute([':item'=>$name,':type'=>$type]); return $this->sanitizeDisplayedPrices($statement->fetchAll());
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
        // Phase 3E: canonical market-price trust gate. Besides the normal basis
        // exclusions, defend against stale pre-3D Armbrace rows that may still
        // contain divided bundle totals or leaked a/k prices. For Armbrace of
        // Truth the stored raw amount must itself be the ecto unit quote.
        $base = "$alias.unit_price_ecto IS NOT NULL AND $alias.unit_price_ecto > 0 AND COALESCE($alias.price_currency,'') IN ('a','e','k') AND COALESCE($alias.price_basis,'') NOT IN ('bundle','currency_exchange','unknown') AND COALESCE($alias.price_basis,'') NOT IN ('currency_conversion','unqualified','uncertain') AND COALESCE($alias.price_quality_status,'trusted')='trusted'";
        $armbrace = "(COALESCE($alias.item_key,'') <> 'armbrace-of-truth' OR (COALESCE($alias.price_currency,'')='e' AND $alias.price_amount IS NOT NULL AND $alias.price_amount > 0 AND $alias.price_amount <= 100 AND ABS($alias.unit_price_ecto-$alias.price_amount) < 0.001))";
        return "$base AND $armbrace";
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function sanitizeDisplayedPrices(array $rows): array
    {
        foreach ($rows as &$row) {
            if (!$this->rowHasTrustedPrice($row)) {
                $row['unit_price_ecto'] = null;
            }
        }
        unset($row);
        return $rows;
    }

    /** @param array<string,mixed> $row */
    private function rowHasTrustedPrice(array $row): bool
    {
        $unit = isset($row['unit_price_ecto']) ? (float)$row['unit_price_ecto'] : 0.0;
        $currency = strtolower((string)($row['price_currency'] ?? ''));
        $basis = strtolower((string)($row['price_basis'] ?? ''));
        if ($unit <= 0 || !in_array($currency, ['a','e','k'], true) || in_array($basis, ['bundle','currency_exchange','unknown','currency_conversion','unqualified','uncertain'], true) || (string)($row['price_quality_status'] ?? 'trusted') !== 'trusted') return false;
        if ((string)($row['item_key'] ?? '') !== 'armbrace-of-truth') return true;
        $amount = isset($row['price_amount']) ? (float)$row['price_amount'] : 0.0;
        return $currency === 'e' && $amount > 0 && $amount <= 100 && abs($unit - $amount) < 0.001;
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
    /** @return array<string,mixed> */
    public function dataQualityOverview(int $issueLimit = 12, int $marketLimit = 20): array
    {
        $summarySql = "SELECT
            COUNT(*) AS offers,
            SUM(CASE WHEN so.quality_status='accepted' THEN 1 ELSE 0 END) AS accepted,
            SUM(CASE WHEN so.quality_status='review' THEN 1 ELSE 0 END) AS parser_review,
            SUM(CASE WHEN so.quality_status='accepted' AND COALESCE(so.lifecycle_status,'active')='active' AND ".$this->trustedPriceExpr('so')." THEN 1 ELSE 0 END) AS trusted_prices,
            SUM(CASE WHEN so.quality_status='accepted' AND COALESCE(so.lifecycle_status,'active')='active' AND COALESCE(so.price_quality_status,'trusted')='uncertain' THEN 1 ELSE 0 END) AS uncertain_prices,
            SUM(CASE WHEN so.quality_status='accepted' AND COALESCE(so.lifecycle_status,'active')='active' AND COALESCE(so.price_quality_status,'trusted')='outlier' THEN 1 ELSE 0 END) AS outlier_prices,
            SUM(CASE WHEN so.quality_status='accepted' AND COALESCE(so.lifecycle_status,'active')='active' AND so.unit_price_ecto IS NULL THEN 1 ELSE 0 END) AS unpriced
            FROM structured_offers so";
        $summary = $this->pdo->query($summarySql)->fetch() ?: [];

        $issuesSql = "SELECT issue_key,label,COUNT(*) AS total FROM (
            SELECT CASE
                WHEN so.quality_status='review' AND COALESCE(so.quality_reason,'')='no_catalog_item' THEN 'no_catalog_item'
                WHEN so.quality_status='review' AND COALESCE(so.quality_reason,'')='low_confidence' THEN 'low_confidence'
                WHEN so.quality_status='review' THEN 'parser_review'
                WHEN COALESCE(so.price_quality_status,'trusted')='outlier' THEN 'outlier'
                WHEN COALESCE(so.price_quality_status,'trusted')='uncertain' THEN 'uncertain'
                WHEN so.quality_status='accepted' AND COALESCE(so.lifecycle_status,'active')='active' AND so.unit_price_ecto IS NULL THEN 'unpriced'
                ELSE NULL
            END AS issue_key,
            CASE
                WHEN so.quality_status='review' AND COALESCE(so.quality_reason,'')='no_catalog_item' THEN 'Parser review: geen catalogusitem'
                WHEN so.quality_status='review' AND COALESCE(so.quality_reason,'')='low_confidence' THEN 'Parser review: lage confidence'
                WHEN so.quality_status='review' THEN 'Parser review: overige'
                WHEN COALESCE(so.price_quality_status,'trusted')='outlier' THEN 'Markt-outliers'
                WHEN COALESCE(so.price_quality_status,'trusted')='uncertain' THEN 'Onzekere prijs / unitprijs'
                WHEN so.quality_status='accepted' AND COALESCE(so.lifecycle_status,'active')='active' AND so.unit_price_ecto IS NULL THEN 'Geen bruikbare geldprijs'
                ELSE NULL
            END AS label
            FROM structured_offers so
        ) q WHERE issue_key IS NOT NULL GROUP BY issue_key,label ORDER BY total DESC,label ASC LIMIT ".max(1,min(50,$issueLimit));
        $issues = $this->pdo->query($issuesSql)->fetchAll();

        $marketSql = "SELECT MIN(so.item) AS item,lower(so.item) AS item_group,
            COUNT(*) AS offers,
            COUNT(DISTINCT lower(m.player)) AS traders,
            SUM(CASE WHEN ".$this->trustedPriceExpr('so')." THEN 1 ELSE 0 END) AS trusted,
            SUM(CASE WHEN COALESCE(so.price_quality_status,'trusted') IN ('uncertain','outlier') THEN 1 ELSE 0 END) AS flagged,
            SUM(CASE WHEN so.unit_price_ecto IS NULL THEN 1 ELSE 0 END) AS unpriced
            FROM structured_offers so
            JOIN messages m ON m.id=so.message_id
            WHERE so.quality_status='accepted' AND COALESCE(so.lifecycle_status,'active')='active'
              AND so.item<>'' AND so.item NOT LIKE 'Bundle:%'
            GROUP BY lower(so.item)
            HAVING COUNT(*) >= 2
            ORDER BY offers DESC
            LIMIT 250";
        $markets = [];
        foreach ($this->pdo->query($marketSql)->fetchAll() as $row) {
            $row['trust'] = $this->calculateMarketTrust($row);
            $markets[] = $row;
        }
        usort($markets, static function(array $a,array $b): int {
            $scoreCmp = ($a['trust']['score'] ?? 0) <=> ($b['trust']['score'] ?? 0);
            return $scoreCmp !== 0 ? $scoreCmp : ((int)$b['offers'] <=> (int)$a['offers']);
        });

        return [
            'summary' => array_map(static fn($v) => is_numeric($v) ? (int)$v : $v, $summary),
            'issues' => $issues,
            'weak_markets' => array_slice($markets,0,max(1,min(50,$marketLimit))),
        ];
    }


    /** @return list<array<string,mixed>> */
    public function dataQualityCases(string $category, string $query = '', string $type = '', int $limit = 200): array
    {
        $allowed=['unpriced','uncertain','outlier','no_catalog_item','low_confidence','parser_review','all'];
        if(!in_array($category,$allowed,true))$category='all';

        $where=[];
        $params=[];
        if($category==='unpriced'){
            $where[]="so.quality_status='accepted' AND COALESCE(so.lifecycle_status,'active')='active' AND so.unit_price_ecto IS NULL";
        }elseif($category==='uncertain'){
            $where[]="so.quality_status='accepted' AND COALESCE(so.price_quality_status,'trusted')='uncertain'";
        }elseif($category==='outlier'){
            $where[]="so.quality_status='accepted' AND COALESCE(so.price_quality_status,'trusted')='outlier'";
        }elseif($category==='no_catalog_item'){
            $where[]="so.quality_status='review' AND COALESCE(so.quality_reason,'')='no_catalog_item'";
        }elseif($category==='low_confidence'){
            $where[]="so.quality_status='review' AND COALESCE(so.quality_reason,'')='low_confidence'";
        }elseif($category==='parser_review'){
            $where[]="so.quality_status='review'";
        }else{
            $where[]="(so.quality_status='review' OR COALESCE(so.price_quality_status,'trusted') IN ('uncertain','outlier') OR (so.quality_status='accepted' AND COALESCE(so.lifecycle_status,'active')='active' AND so.unit_price_ecto IS NULL))";
        }

        if(in_array($type,['buy','sell','trade'],true)){
            $where[]='so.trade_type=:type';
            $params[':type']=$type;
        }
        if($query!==''){
            $where[]='(so.item LIKE :q OR so.raw_segment LIKE :q OR m.player LIKE :q OR m.message LIKE :q OR COALESCE(so.quality_reason,\'\') LIKE :q OR COALESCE(so.price_quality_reason,\'\') LIKE :q)';
            $params[':q']='%'.$query.'%';
        }

        $sql="SELECT so.id,so.message_id,so.trade_type,so.item,so.item_key,so.raw_segment,so.confidence,
                     so.quality_status,so.quality_reason,so.price_amount,so.price_currency,so.unit_price_ecto,
                     so.price_basis,so.price_quality_status,so.price_quality_reason,so.price_outlier_score,
                     so.price_baseline_ecto,so.lifecycle_status,m.player,m.message,m.posted_at
              FROM structured_offers so JOIN messages m ON m.id=so.message_id
              WHERE ".implode(' AND ',$where)."
              ORDER BY datetime(m.posted_at) DESC,so.id DESC
              LIMIT ".max(1,min(500,$limit));
        $statement=$this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return array{score:int,label:string,coverage:int,traders:int,flagged:int,unpriced:int,offers:int,trusted:int} */
    public function marketTrustForItem(string $name): array
    {
        $statement=$this->pdo->prepare("SELECT COUNT(*) AS offers,COUNT(DISTINCT lower(m.player)) AS traders,
            SUM(CASE WHEN ".$this->trustedPriceExpr('so')." THEN 1 ELSE 0 END) AS trusted,
            SUM(CASE WHEN COALESCE(so.price_quality_status,'trusted') IN ('uncertain','outlier') THEN 1 ELSE 0 END) AS flagged,
            SUM(CASE WHEN so.unit_price_ecto IS NULL THEN 1 ELSE 0 END) AS unpriced
            FROM structured_offers so JOIN messages m ON m.id=so.message_id
            WHERE lower(so.item)=lower(:item) AND so.quality_status='accepted' AND COALESCE(so.lifecycle_status,'active')='active'");
        $statement->execute([':item'=>$name]);
        return $this->calculateMarketTrust($statement->fetch() ?: []);
    }

    /** @param array<string,mixed> $row
     *  @return array{score:int,label:string,coverage:int,traders:int,flagged:int,unpriced:int,offers:int,trusted:int}
     */
    private function calculateMarketTrust(array $row): array
    {
        $offers=max(0,(int)($row['offers']??0));
        $trusted=max(0,(int)($row['trusted']??0));
        $traders=max(0,(int)($row['traders']??0));
        $flagged=max(0,(int)($row['flagged']??0));
        $unpriced=max(0,(int)($row['unpriced']??0));

        $coverage=$offers>0?(int)round(min(1,$trusted/$offers)*100):0;
        // Trust combines usable-price coverage, independent-trader diversity and sample depth.
        // Flagged prices are explicitly penalised. Missing prices lower coverage but are not treated as parser failures.
        $coveragePoints=45*($coverage/100);
        $traderPoints=25*min(1,$traders/8);
        $samplePoints=15*min(1,$trusted/12);
        $flagPenalty=$offers>0?25*min(1,$flagged/max(1,$offers)):0;
        $score=(int)round(max(0,min(100,$coveragePoints+$traderPoints+$samplePoints+15-$flagPenalty)));
        $label=$score>=85?'Zeer sterk':($score>=70?'Sterk':($score>=50?'Redelijk':($score>=30?'Zwak':'Zeer zwak')));

        return compact('score','label','coverage','traders','flagged','unpriced','offers','trusted');
    }

}
