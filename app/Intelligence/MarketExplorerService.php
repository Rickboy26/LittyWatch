<?php

declare(strict_types=1);

namespace LittyWatch\Intelligence;

use PDO;

final class MarketExplorerService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function search(array $filters = [], int $limit = 100): array
    {
        Schema::ensure($this->pdo);

        $where = ['1=1'];
        $params = [];

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(mi.item LIKE :q OR mi.market_key LIKE :q)';
            $params[':q'] = '%' . $query . '%';
        }

        $minimumConfidence = max(0, min(100, (int)($filters['confidence'] ?? 0)));
        if ($minimumConfidence > 0) {
            $where[] = 'mi.confidence_score >= :confidence';
            $params[':confidence'] = $minimumConfidence;
        }

        $minimumLiquidity = max(0, min(100, (int)($filters['liquidity'] ?? 0)));
        if ($minimumLiquidity > 0) {
            $where[] = 'mi.liquidity_score >= :liquidity';
            $params[':liquidity'] = $minimumLiquidity;
        }

        $side = (string)($filters['side'] ?? '');
        if ($side === 'both') {
            $where[] = 'mi.best_wtb_ecto IS NOT NULL AND mi.best_wts_ecto IS NOT NULL';
        } elseif ($side === 'buy') {
            $where[] = 'mi.best_wtb_ecto IS NOT NULL';
        } elseif ($side === 'sell') {
            $where[] = 'mi.best_wts_ecto IS NOT NULL';
        }

        $sort = (string)($filters['sort'] ?? 'activity');
        $order = match ($sort) {
            'deal' => 'mi.deal_score DESC, mi.confidence_score DESC',
            'liquidity' => 'mi.liquidity_score DESC, mi.unique_traders DESC',
            'confidence' => 'mi.confidence_score DESC, mi.unique_traders DESC',
            'demand' => 'mi.demand_score DESC, mi.buy_offers DESC',
            'supply' => 'mi.demand_score ASC, mi.sell_offers DESC',
            'item' => 'mi.item COLLATE NOCASE ASC, mi.market_key COLLATE NOCASE ASC',
            default => 'mi.last_activity DESC, mi.unique_traders DESC',
        };

        $sql = 'SELECT mi.*, EXISTS(SELECT 1 FROM watchlist w WHERE w.market_key = mi.market_key) AS watched '
            . 'FROM market_intelligence mi WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $order . ' LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function detail(string $marketKey): ?array
    {
        Schema::ensure($this->pdo);
        $stmt = $this->pdo->prepare(<<<'SQL'
SELECT mi.*, EXISTS(SELECT 1 FROM watchlist w WHERE w.market_key = mi.market_key) AS watched
FROM market_intelligence mi
WHERE mi.market_key = :market_key
LIMIT 1
SQL);
        $stmt->execute([':market_key' => $marketKey]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function offers(string $marketKey, int $limit = 100): array
    {
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
    m.player,
    m.message,
    m.posted_at
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
WHERE {$marketExpr} = :market_key
  {$quality}
  {$lifecycle}
ORDER BY m.id DESC, so.id DESC
LIMIT :limit
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':market_key', $marketKey, PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min(300, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function history(string $marketKey, int $days = 30): array
    {
        if (!$this->tableExists('market_snapshots')) {
            return [];
        }
        $days = max(1, min(365, $days));
        $stmt = $this->pdo->prepare(<<<'SQL'
SELECT captured_at, best_wtb_ecto, best_wts_ecto, median_wtb_ecto, median_wts_ecto, active_offers, unique_traders
FROM market_snapshots
WHERE market_key = :market_key
  AND captured_at >= datetime('now', :window)
ORDER BY captured_at ASC
LIMIT 1000
SQL);
        $stmt->execute([':market_key' => $marketKey, ':window' => '-' . $days . ' days']);
        return $stmt->fetchAll();
    }

    public function addWatch(string $marketKey, string $label): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS watchlist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    market_key TEXT NOT NULL UNIQUE,
    label TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        $stmt = $this->pdo->prepare('INSERT INTO watchlist (market_key, label) VALUES (:key, :label) ON CONFLICT(market_key) DO UPDATE SET label = excluded.label');
        $stmt->execute([':key' => trim($marketKey), ':label' => trim($label)]);
    }

    public function removeWatch(string $marketKey): void
    {
        if (!$this->tableExists('watchlist')) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM watchlist WHERE market_key = :key');
        $stmt->execute([':key' => $marketKey]);
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        Schema::ensure($this->pdo);
        $row = $this->pdo->query(<<<'SQL'
SELECT
    COUNT(*) AS markets,
    SUM(CASE WHEN best_wtb_ecto IS NOT NULL AND best_wts_ecto IS NOT NULL THEN 1 ELSE 0 END) AS two_sided,
    SUM(CASE WHEN deal_score >= 60 THEN 1 ELSE 0 END) AS deals,
    SUM(CASE WHEN confidence_score >= 80 THEN 1 ELSE 0 END) AS high_confidence
FROM market_intelligence
SQL)->fetch();
        return [
            'markets' => (int)($row['markets'] ?? 0),
            'two_sided' => (int)($row['two_sided'] ?? 0),
            'deals' => (int)($row['deals'] ?? 0),
            'high_confidence' => (int)($row['high_confidence'] ?? 0),
        ];
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
