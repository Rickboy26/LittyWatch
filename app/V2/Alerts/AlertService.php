<?php

declare(strict_types=1);

namespace LittyWatch\V2\Alerts;

use PDO;

final class AlertService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function install(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    market_key TEXT NOT NULL,
    label TEXT NOT NULL DEFAULT '',
    condition_type TEXT NOT NULL,
    threshold_ecto REAL,
    is_enabled INTEGER NOT NULL DEFAULT 1,
    last_triggered_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS alert_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    alert_id INTEGER NOT NULL,
    market_key TEXT NOT NULL,
    event_type TEXT NOT NULL,
    observed_value_ecto REAL,
    message TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(alert_id, message, created_at)
)
SQL);

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_alerts_market ON alerts(market_key)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_alert_events_alert ON alert_events(alert_id, created_at)');
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $this->install();
        $sql = <<<SQL
SELECT
    a.*,
    mi.item,
    mi.best_wtb_ecto,
    mi.best_wts_ecto,
    mi.median_wtb_ecto,
    mi.median_wts_ecto,
    mi.deal_score,
    mi.confidence_score
FROM alerts a
LEFT JOIN market_intelligence mi ON mi.market_key = a.market_key
ORDER BY a.is_enabled DESC, a.id DESC
SQL;

        return $this->pdo->query($sql)->fetchAll();
    }

    public function create(string $marketKey, string $label, string $type, ?float $threshold): int
    {
        $this->install();

        $allowed = ['wts_below', 'wtb_above', 'spread_above', 'new_offer'];
        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException('Ongeldig alerttype.');
        }

        if ($type !== 'new_offer' && ($threshold === null || $threshold <= 0)) {
            throw new \InvalidArgumentException('Voer een geldige ectodrempel in.');
        }

        $stmt = $this->pdo->prepare(<<<'SQL'
INSERT INTO alerts (market_key, label, condition_type, threshold_ecto)
VALUES (:market_key, :label, :condition_type, :threshold_ecto)
SQL);
        $stmt->execute([
            ':market_key' => trim($marketKey),
            ':label' => trim($label),
            ':condition_type' => $type,
            ':threshold_ecto' => $threshold,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function toggle(int $id): void
    {
        $this->install();
        $stmt = $this->pdo->prepare(
            'UPDATE alerts SET is_enabled = CASE WHEN is_enabled = 1 THEN 0 ELSE 1 END WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->install();
        $this->pdo->prepare('DELETE FROM alert_events WHERE alert_id = :id')->execute([':id' => $id]);
        $this->pdo->prepare('DELETE FROM alerts WHERE id = :id')->execute([':id' => $id]);
    }

    /** @return array<string,int> */
    public function evaluate(): array
    {
        $this->install();

        if (!$this->tableExists('market_intelligence')) {
            return ['checked' => 0, 'triggered' => 0];
        }

        $alerts = $this->pdo->query("SELECT * FROM alerts WHERE is_enabled = 1 ORDER BY id")->fetchAll();
        $checked = 0;
        $triggered = 0;

        foreach ($alerts as $alert) {
            $checked++;
            $stmt = $this->pdo->prepare('SELECT * FROM market_intelligence WHERE market_key = :market_key LIMIT 1');
            $stmt->execute([':market_key' => $alert['market_key']]);
            $market = $stmt->fetch();
            if (!$market) {
                continue;
            }

            $result = $this->matches($alert, $market);
            if ($result === null) {
                continue;
            }

            [$eventType, $value, $message] = $result;

            if ($this->recentDuplicate((int)$alert['id'], $message)) {
                continue;
            }

            $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO alert_events (alert_id, market_key, event_type, observed_value_ecto, message)
VALUES (:alert_id, :market_key, :event_type, :observed_value_ecto, :message)
SQL);
            $insert->execute([
                ':alert_id' => $alert['id'],
                ':market_key' => $alert['market_key'],
                ':event_type' => $eventType,
                ':observed_value_ecto' => $value,
                ':message' => $message,
            ]);

            $this->pdo->prepare('UPDATE alerts SET last_triggered_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute([':id' => $alert['id']]);

            $triggered++;
        }

        return ['checked' => $checked, 'triggered' => $triggered];
    }

    /** @return array<int,array<string,mixed>> */
    public function events(int $limit = 100): array
    {
        $this->install();
        $stmt = $this->pdo->prepare(<<<'SQL'
SELECT e.*, a.label, a.condition_type
FROM alert_events e
JOIN alerts a ON a.id = e.alert_id
ORDER BY e.id DESC
LIMIT :limit
SQL);
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @param array<string,mixed> $alert
     * @param array<string,mixed> $market
     * @return array{0:string,1:float,2:string}|null
     */
    private function matches(array $alert, array $market): ?array
    {
        $type = (string)$alert['condition_type'];
        $threshold = $alert['threshold_ecto'] !== null ? (float)$alert['threshold_ecto'] : null;
        $item = (string)($market['item'] ?? $alert['market_key']);

        if ($type === 'wts_below' && $threshold !== null && (float)$market['best_wts_ecto'] > 0 && (float)$market['best_wts_ecto'] <= $threshold) {
            $value = (float)$market['best_wts_ecto'];
            return [$type, $value, "{$item}: WTS {$value}e is onder of gelijk aan {$threshold}e."];
        }

        if ($type === 'wtb_above' && $threshold !== null && (float)$market['best_wtb_ecto'] >= $threshold) {
            $value = (float)$market['best_wtb_ecto'];
            return [$type, $value, "{$item}: WTB {$value}e is boven of gelijk aan {$threshold}e."];
        }

        if ($type === 'spread_above' && $threshold !== null) {
            $wtb = (float)$market['best_wtb_ecto'];
            $wts = (float)$market['best_wts_ecto'];
            $spread = $wtb - $wts;
            if ($wtb > 0 && $wts > 0 && $spread >= $threshold) {
                return [$type, $spread, "{$item}: spread {$spread}e (WTB {$wtb}e / WTS {$wts}e)."];
            }
        }

        if ($type === 'new_offer') {
            $lastActivity = (string)($market['last_activity'] ?? '');
            if ($lastActivity !== '' && strtotime($lastActivity) >= time() - 900) {
                return [$type, 0.0, "{$item}: nieuwe marktactiviteit om {$lastActivity}."];
            }
        }

        return null;
    }

    private function recentDuplicate(int $alertId, string $message): bool
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
SELECT 1
FROM alert_events
WHERE alert_id = :alert_id
  AND message = :message
  AND created_at >= datetime('now', '-30 minutes')
LIMIT 1
SQL);
        $stmt->execute([':alert_id' => $alertId, ':message' => $message]);
        return (bool)$stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
        $stmt->execute([':name' => $table]);
        return (bool)$stmt->fetchColumn();
    }
}
