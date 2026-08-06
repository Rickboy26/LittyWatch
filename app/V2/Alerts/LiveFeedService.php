<?php

declare(strict_types=1);

namespace LittyWatch\V2\Alerts;

use PDO;

final class LiveFeedService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function latest(int $limit = 100): array
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
    so.unit_price_ecto,
    so.price_amount,
    so.price_currency,
    so.price_basis,
    so.quantity,
    so.confidence,
    so.raw_segment,
    m.player,
    m.posted_at,
    mi.median_wtb_ecto,
    mi.median_wts_ecto,
    mi.deal_score,
    mi.confidence_score
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
LEFT JOIN market_intelligence mi ON mi.market_key = {$marketExpr}
WHERE 1=1
  {$quality}
  {$lifecycle}
ORDER BY m.id DESC, so.id DESC
LIMIT :limit
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['deal_label'] = $this->dealLabel($row);
            $row['difference_percent'] = $this->differencePercent($row);
        }
        unset($row);

        return $rows;
    }

    /** @param array<string,mixed> $row */
    private function dealLabel(array $row): string
    {
        $price = (float)($row['unit_price_ecto'] ?? 0);
        if ($price <= 0) {
            return 'Geen prijs';
        }

        $type = strtolower((string)($row['trade_type'] ?? ''));
        $median = $type === 'sell'
            ? (float)($row['median_wts_ecto'] ?? 0)
            : (float)($row['median_wtb_ecto'] ?? 0);

        if ($median <= 0) {
            return 'Nieuwe prijs';
        }

        $ratio = ($price - $median) / $median;
        if ($type === 'sell') {
            return match (true) {
                $ratio <= -0.15 => 'Zeer goedkoop',
                $ratio <= -0.05 => 'Onder markt',
                $ratio >= 0.15 => 'Duur',
                default => 'Rond mediaan',
            };
        }

        return match (true) {
            $ratio >= 0.15 => 'Zeer sterke WTB',
            $ratio >= 0.05 => 'Boven markt',
            $ratio <= -0.15 => 'Lage WTB',
            default => 'Rond mediaan',
        };
    }

    /** @param array<string,mixed> $row */
    private function differencePercent(array $row): ?float
    {
        $price = (float)($row['unit_price_ecto'] ?? 0);
        $type = strtolower((string)($row['trade_type'] ?? ''));
        $median = $type === 'sell'
            ? (float)($row['median_wts_ecto'] ?? 0)
            : (float)($row['median_wtb_ecto'] ?? 0);

        if ($price <= 0 || $median <= 0) {
            return null;
        }

        return round((($price - $median) / $median) * 100, 1);
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
