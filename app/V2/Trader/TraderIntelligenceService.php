<?php

declare(strict_types=1);

namespace LittyWatch\V2\Trader;

use PDO;

final class TraderIntelligenceService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function search(string $query = '', string $sort = 'activity', int $limit = 200): array
    {
        if (!$this->tableExists('messages')) {
            return [];
        }

        $where = ["TRIM(COALESCE(m.player, '')) <> ''"];
        $params = [];

        $query = trim($query);
        if ($query !== '') {
            $where[] = 'm.player LIKE :query';
            $params[':query'] = '%' . $query . '%';
        }

        $order = match ($sort) {
            'offers' => 'offers_count DESC, last_seen DESC',
            'markets' => 'market_count DESC, offers_count DESC',
            'buy' => 'buy_count DESC, offers_count DESC',
            'sell' => 'sell_count DESC, offers_count DESC',
            'confidence' => 'average_confidence DESC, offers_count DESC',
            'name' => 'm.player COLLATE NOCASE ASC',
            default => 'last_seen DESC, offers_count DESC',
        };

        $structured = $this->tableExists('structured_offers');
        if ($structured) {
            $marketExpr = $this->columnExists('structured_offers', 'normalized_market_key')
                ? "COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key)"
                : 'so.market_key';
            $quality = $this->columnExists('structured_offers', 'quality_status')
                ? "AND COALESCE(so.quality_status, 'review') = 'accepted'"
                : '';
            $lifecycle = $this->columnExists('structured_offers', 'lifecycle_status')
                ? "AND COALESCE(so.lifecycle_status, 'active') = 'active'"
                : '';

            $sql = <<<SQL
SELECT
    m.player,
    COUNT(so.id) AS offers_count,
    SUM(CASE WHEN LOWER(so.trade_type) = 'buy' THEN 1 ELSE 0 END) AS buy_count,
    SUM(CASE WHEN LOWER(so.trade_type) = 'sell' THEN 1 ELSE 0 END) AS sell_count,
    COUNT(DISTINCT NULLIF({$marketExpr}, '')) AS market_count,
    COUNT(DISTINCT NULLIF(so.item, '')) AS item_count,
    AVG(COALESCE(so.confidence, 0)) AS average_confidence,
    SUM(CASE WHEN so.unit_price_ecto IS NOT NULL AND so.unit_price_ecto > 0 THEN 1 ELSE 0 END) AS priced_offers,
    MIN(m.posted_at) AS first_seen,
    MAX(m.posted_at) AS last_seen
FROM messages m
JOIN structured_offers so ON so.message_id = m.id
WHERE %s
  {$quality}
  {$lifecycle}
GROUP BY m.player
ORDER BY {$order}
LIMIT :limit
SQL;
        } else {
            $sql = <<<SQL
SELECT
    m.player,
    COUNT(o.id) AS offers_count,
    SUM(CASE WHEN LOWER(o.trade_type) = 'buy' THEN 1 ELSE 0 END) AS buy_count,
    SUM(CASE WHEN LOWER(o.trade_type) = 'sell' THEN 1 ELSE 0 END) AS sell_count,
    COUNT(DISTINCT NULLIF(o.item_key, '')) AS market_count,
    COUNT(DISTINCT NULLIF(o.item, '')) AS item_count,
    AVG(COALESCE(o.confidence, 0)) AS average_confidence,
    SUM(CASE WHEN o.unit_price_ecto IS NOT NULL AND o.unit_price_ecto > 0 THEN 1 ELSE 0 END) AS priced_offers,
    MIN(m.posted_at) AS first_seen,
    MAX(m.posted_at) AS last_seen
FROM messages m
JOIN offers o ON o.message_id = m.id
WHERE %s
GROUP BY m.player
ORDER BY {$order}
LIMIT :limit
SQL;
        }

        $sql = sprintf($sql, implode(' AND ', $where));
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['activity_score'] = $this->activityScore($row);
            $row['reliability_score'] = $this->reliabilityScore($row);
            $row['side_label'] = $this->sideLabel((int)($row['buy_count'] ?? 0), (int)($row['sell_count'] ?? 0));
        }
        unset($row);

        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function profile(string $player): ?array
    {
        $player = trim($player);
        if ($player === '') {
            return null;
        }

        $rows = $this->search($player, 'name', 20);
        foreach ($rows as $row) {
            if (strcasecmp((string)$row['player'], $player) === 0) {
                return $row;
            }
        }

        return null;
    }

    /** @return array<int,array<string,mixed>> */
    public function recentOffers(string $player, int $limit = 150): array
    {
        if (!$this->tableExists('structured_offers')) {
            return [];
        }

        $marketExpr = $this->columnExists('structured_offers', 'normalized_market_key')
            ? "COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key)"
            : 'so.market_key';
        $quality = $this->columnExists('structured_offers', 'quality_status')
            ? "AND COALESCE(so.quality_status, 'review') = 'accepted'"
            : '';
        $lifecycle = $this->columnExists('structured_offers', 'lifecycle_status')
            ? "AND COALESCE(so.lifecycle_status, 'active') = 'active'"
            : '';

        $sql = <<<SQL
SELECT
    so.id,
    so.trade_type,
    so.item,
    {$marketExpr} AS market_key,
    so.requirement,
    so.attribute_name,
    so.is_oldschool,
    so.is_inscribable,
    so.mods_json,
    so.quantity,
    so.price_amount,
    so.price_currency,
    so.unit_price_ecto,
    so.price_basis,
    so.confidence,
    so.raw_segment,
    m.message,
    m.posted_at
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
WHERE m.player = :player
  {$quality}
  {$lifecycle}
ORDER BY m.id DESC, so.id DESC
LIMIT :limit
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':player', $player, PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function topMarkets(string $player, int $limit = 20): array
    {
        if (!$this->tableExists('structured_offers')) {
            return [];
        }

        $marketExpr = $this->columnExists('structured_offers', 'normalized_market_key')
            ? "COALESCE(NULLIF(so.normalized_market_key, ''), so.market_key)"
            : 'so.market_key';
        $quality = $this->columnExists('structured_offers', 'quality_status')
            ? "AND COALESCE(so.quality_status, 'review') = 'accepted'"
            : '';

        $sql = <<<SQL
SELECT
    {$marketExpr} AS market_key,
    MIN(so.item) AS item,
    COUNT(*) AS offers_count,
    SUM(CASE WHEN LOWER(so.trade_type) = 'buy' THEN 1 ELSE 0 END) AS buy_count,
    SUM(CASE WHEN LOWER(so.trade_type) = 'sell' THEN 1 ELSE 0 END) AS sell_count,
    AVG(CASE WHEN so.unit_price_ecto > 0 THEN so.unit_price_ecto END) AS average_price_ecto,
    MAX(m.posted_at) AS last_seen
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
WHERE m.player = :player
  AND COALESCE({$marketExpr}, '') <> ''
  {$quality}
GROUP BY {$marketExpr}
ORDER BY offers_count DESC, last_seen DESC
LIMIT :limit
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':player', $player, PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        if (!$this->tableExists('messages')) {
            return ['traders' => 0, 'active_24h' => 0, 'offers' => 0, 'priced' => 0];
        }

        $row = $this->pdo->query(<<<'SQL'
SELECT
    COUNT(DISTINCT NULLIF(TRIM(player), '')) AS traders,
    COUNT(DISTINCT CASE WHEN posted_at >= datetime('now', '-1 day') THEN NULLIF(TRIM(player), '') END) AS active_24h,
    COUNT(*) AS messages
FROM messages
SQL)->fetch() ?: [];

        $offers = 0;
        $priced = 0;
        if ($this->tableExists('structured_offers')) {
            $offerRow = $this->pdo->query(<<<'SQL'
SELECT
    COUNT(*) AS offers,
    SUM(CASE WHEN unit_price_ecto IS NOT NULL AND unit_price_ecto > 0 THEN 1 ELSE 0 END) AS priced
FROM structured_offers
SQL)->fetch() ?: [];
            $offers = (int)($offerRow['offers'] ?? 0);
            $priced = (int)($offerRow['priced'] ?? 0);
        }

        return [
            'traders' => (int)($row['traders'] ?? 0),
            'active_24h' => (int)($row['active_24h'] ?? 0),
            'offers' => $offers,
            'priced' => $priced,
        ];
    }

    /** @param array<string,mixed> $row */
    private function activityScore(array $row): int
    {
        $offers = (int)($row['offers_count'] ?? 0);
        $markets = (int)($row['market_count'] ?? 0);
        $priced = (int)($row['priced_offers'] ?? 0);

        return max(0, min(100, (int)round(($offers * 1.6) + ($markets * 4) + ($priced * 0.8))));
    }

    /** @param array<string,mixed> $row */
    private function reliabilityScore(array $row): int
    {
        $offers = max(1, (int)($row['offers_count'] ?? 0));
        $priced = (int)($row['priced_offers'] ?? 0);
        $confidence = (float)($row['average_confidence'] ?? 0);
        $pricedRatio = min(1, $priced / $offers);

        return max(0, min(100, (int)round(($confidence * 0.65) + ($pricedRatio * 25) + min(10, $offers / 5))));
    }

    private function sideLabel(int $buy, int $sell): string
    {
        $total = $buy + $sell;
        if ($total === 0) {
            return 'Onbekend';
        }
        $buyRatio = $buy / $total;
        return match (true) {
            $buyRatio >= 0.7 => 'Vooral koper',
            $buyRatio <= 0.3 => 'Vooral verkoper',
            default => 'Koper & verkoper',
        };
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
        $stmt->execute([':name' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        foreach ($this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }
        return false;
    }
}
