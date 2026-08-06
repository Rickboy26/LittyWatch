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
    source TEXT NOT NULL DEFAULT 'manual',
    is_enabled INTEGER NOT NULL DEFAULT 1,
    condition_met INTEGER NOT NULL DEFAULT 0,
    last_signature TEXT,
    last_checked_at TEXT,
    last_triggered_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
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
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(alert_id) REFERENCES alerts(id) ON DELETE CASCADE
)
SQL);
        foreach ([
            ['alerts', 'source', "TEXT NOT NULL DEFAULT 'manual'"],
            ['alerts', 'condition_met', 'INTEGER NOT NULL DEFAULT 0'],
            ['alerts', 'last_signature', 'TEXT'],
            ['alerts', 'last_checked_at', 'TEXT'],
            ['alerts', 'updated_at', "TEXT NOT NULL DEFAULT ''"],
            ['alert_events', 'is_read', 'INTEGER NOT NULL DEFAULT 0'],
        ] as [$table, $column, $definition]) {
            $this->ensureColumn($table, $column, $definition);
        }
        $this->pdo->exec("UPDATE alerts SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL OR updated_at = ''");
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_alerts_market ON alerts(market_key)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_alerts_enabled ON alerts(is_enabled, condition_type)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_alert_events_alert ON alert_events(alert_id, created_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_alert_events_unread ON alert_events(is_read, created_at)');
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $this->install();
        return $this->pdo->query(<<<'SQL'
SELECT
    a.*,
    mi.item,
    mi.best_wtb_ecto,
    mi.best_wts_ecto,
    mi.median_wtb_ecto,
    mi.median_wts_ecto,
    mi.last_activity,
    mi.deal_score,
    mi.confidence_score
FROM alerts a
LEFT JOIN market_intelligence mi ON mi.market_key = a.market_key
ORDER BY a.is_enabled DESC, a.updated_at DESC, a.id DESC
SQL)->fetchAll();
    }

    public function create(string $marketKey, string $label, string $type, ?float $threshold): int
    {
        $this->install();
        $this->validate($marketKey, $type, $threshold);
        $stmt = $this->pdo->prepare(<<<'SQL'
INSERT INTO alerts (market_key, label, condition_type, threshold_ecto, source, updated_at)
VALUES (:market_key, :label, :condition_type, :threshold_ecto, 'manual', CURRENT_TIMESTAMP)
SQL);
        $stmt->execute([
            ':market_key' => trim($marketKey),
            ':label' => trim($label),
            ':condition_type' => $type,
            ':threshold_ecto' => $threshold,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function syncWatchlistTargets(
        string $marketKey,
        string $label,
        ?float $targetBuyEcto,
        ?float $targetSellEcto
    ): void {
        $this->install();
        $this->pdo->beginTransaction();
        try {
            $this->upsertGenerated($marketKey, $label, 'wts_below', $targetBuyEcto);
            $this->upsertGenerated($marketKey, $label, 'wtb_above', $targetSellEcto);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function removeWatchlistAlerts(string $marketKey): void
    {
        $this->install();
        $stmt = $this->pdo->prepare("DELETE FROM alerts WHERE market_key = :key AND source = 'watchlist'");
        $stmt->execute([':key' => $marketKey]);
    }

    public function toggle(int $id): void
    {
        $this->install();
        $stmt = $this->pdo->prepare(<<<'SQL'
UPDATE alerts
SET is_enabled = CASE WHEN is_enabled = 1 THEN 0 ELSE 1 END,
    condition_met = 0,
    last_signature = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :id
SQL);
        $stmt->execute([':id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->install();
        $this->pdo->prepare('DELETE FROM alert_events WHERE alert_id = :id')->execute([':id' => $id]);
        $this->pdo->prepare('DELETE FROM alerts WHERE id = :id')->execute([':id' => $id]);
    }

    /** @return array{checked:int,triggered:int,reset:int} */
    public function evaluate(): array
    {
        $this->install();
        if (!$this->tableExists('market_intelligence')) {
            return ['checked' => 0, 'triggered' => 0, 'reset' => 0];
        }

        $alerts = $this->pdo->query("SELECT * FROM alerts WHERE is_enabled = 1 ORDER BY id")->fetchAll();
        $checked = 0;
        $triggered = 0;
        $reset = 0;

        foreach ($alerts as $alert) {
            $checked++;
            $stmt = $this->pdo->prepare('SELECT * FROM market_intelligence WHERE market_key = :market_key LIMIT 1');
            $stmt->execute([':market_key' => $alert['market_key']]);
            $market = $stmt->fetch();
            if (!$market) {
                $this->markChecked((int)$alert['id'], false, null);
                continue;
            }

            $result = $this->matches($alert, $market);
            if ($result === null) {
                if ((int)$alert['condition_met'] === 1) {
                    $reset++;
                }
                $this->markChecked((int)$alert['id'], false, null);
                continue;
            }

            [$eventType, $value, $message, $signature] = $result;
            $isNewTransition = (int)$alert['condition_met'] !== 1;
            $isNewSignature = $signature !== '' && $signature !== (string)($alert['last_signature'] ?? '');

            if ($isNewTransition || $isNewSignature) {
                $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO alert_events (alert_id, market_key, event_type, observed_value_ecto, message, is_read)
VALUES (:alert_id, :market_key, :event_type, :observed_value_ecto, :message, 0)
SQL);
                $insert->execute([
                    ':alert_id' => $alert['id'],
                    ':market_key' => $alert['market_key'],
                    ':event_type' => $eventType,
                    ':observed_value_ecto' => $value,
                    ':message' => $message,
                ]);
                $triggered++;
                $this->pdo->prepare('UPDATE alerts SET last_triggered_at = CURRENT_TIMESTAMP WHERE id = :id')
                    ->execute([':id' => $alert['id']]);
            }
            $this->markChecked((int)$alert['id'], true, $signature);
        }

        return ['checked' => $checked, 'triggered' => $triggered, 'reset' => $reset];
    }

    /** @return array<int,array<string,mixed>> */
    public function events(int $limit = 100, bool $unreadOnly = false): array
    {
        $this->install();
        $where = $unreadOnly ? 'WHERE e.is_read = 0' : '';
        $stmt = $this->pdo->prepare(<<<SQL
SELECT e.*, a.label, a.condition_type, a.source
FROM alert_events e
JOIN alerts a ON a.id = e.alert_id
{$where}
ORDER BY e.id DESC
LIMIT :limit
SQL);
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function unreadCount(): int
    {
        $this->install();
        return (int)$this->pdo->query('SELECT COUNT(*) FROM alert_events WHERE is_read = 0')->fetchColumn();
    }

    public function markRead(int $eventId): void
    {
        $this->install();
        $this->pdo->prepare('UPDATE alert_events SET is_read = 1 WHERE id = :id')->execute([':id' => $eventId]);
    }

    public function markAllRead(): void
    {
        $this->install();
        $this->pdo->exec('UPDATE alert_events SET is_read = 1 WHERE is_read = 0');
    }

    private function upsertGenerated(string $marketKey, string $label, string $type, ?float $threshold): void
    {
        $find = $this->pdo->prepare("SELECT id FROM alerts WHERE market_key = :key AND condition_type = :type AND source = 'watchlist' LIMIT 1");
        $find->execute([':key' => $marketKey, ':type' => $type]);
        $id = $find->fetchColumn();

        if ($threshold === null) {
            if ($id !== false) {
                $this->delete((int)$id);
            }
            return;
        }

        $this->validate($marketKey, $type, $threshold);
        $generatedLabel = trim($label) !== '' ? trim($label) : $marketKey;
        if ($id === false) {
            $stmt = $this->pdo->prepare(<<<'SQL'
INSERT INTO alerts (market_key, label, condition_type, threshold_ecto, source, updated_at)
VALUES (:key, :label, :type, :threshold, 'watchlist', CURRENT_TIMESTAMP)
SQL);
            $stmt->execute([':key' => $marketKey, ':label' => $generatedLabel, ':type' => $type, ':threshold' => $threshold]);
            return;
        }

        $stmt = $this->pdo->prepare(<<<'SQL'
UPDATE alerts
SET label = :label,
    threshold_ecto = :threshold,
    is_enabled = 1,
    condition_met = 0,
    last_signature = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :id
SQL);
        $stmt->execute([':label' => $generatedLabel, ':threshold' => $threshold, ':id' => $id]);
    }

    /** @param array<string,mixed> $alert @param array<string,mixed> $market
     *  @return array{0:string,1:float,2:string,3:string}|null */
    private function matches(array $alert, array $market): ?array
    {
        $type = (string)$alert['condition_type'];
        $threshold = $alert['threshold_ecto'] !== null ? (float)$alert['threshold_ecto'] : null;
        $item = (string)($market['item'] ?? $alert['market_key']);

        if ($type === 'wts_below' && $threshold !== null) {
            $value = (float)($market['best_wts_ecto'] ?? 0);
            if ($value > 0 && $value <= $threshold) {
                return [$type, $value, "{$item}: goedkoopste WTS is {$value}e (doel maximaal {$threshold}e).", 'wts:' . $value];
            }
        }
        if ($type === 'wtb_above' && $threshold !== null) {
            $value = (float)($market['best_wtb_ecto'] ?? 0);
            if ($value > 0 && $value >= $threshold) {
                return [$type, $value, "{$item}: hoogste WTB is {$value}e (doel minimaal {$threshold}e).", 'wtb:' . $value];
            }
        }
        if ($type === 'spread_above' && $threshold !== null) {
            $wtb = (float)($market['best_wtb_ecto'] ?? 0);
            $wts = (float)($market['best_wts_ecto'] ?? 0);
            $spread = $wtb - $wts;
            if ($wtb > 0 && $wts > 0 && $spread >= $threshold) {
                return [$type, $spread, "{$item}: spread {$spread}e (WTB {$wtb}e / WTS {$wts}e).", 'spread:' . $wtb . ':' . $wts];
            }
        }
        if ($type === 'new_offer') {
            $lastActivity = (string)($market['last_activity'] ?? '');
            if ($lastActivity !== '' && strtotime($lastActivity) >= time() - 1800) {
                return [$type, 0.0, "{$item}: nieuwe marktactiviteit om {$lastActivity}.", 'activity:' . $lastActivity];
            }
        }
        return null;
    }

    private function validate(string $marketKey, string $type, ?float $threshold): void
    {
        if (trim($marketKey) === '') {
            throw new \InvalidArgumentException('Kies een marktvariant.');
        }
        $allowed = ['wts_below', 'wtb_above', 'spread_above', 'new_offer'];
        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException('Ongeldig alerttype.');
        }
        if ($type !== 'new_offer' && ($threshold === null || $threshold <= 0)) {
            throw new \InvalidArgumentException('Voer een geldige ectodrempel in.');
        }
    }

    private function markChecked(int $id, bool $conditionMet, ?string $signature): void
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
UPDATE alerts
SET condition_met = :condition_met,
    last_signature = :signature,
    last_checked_at = CURRENT_TIMESTAMP,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :id
SQL);
        $stmt->execute([
            ':condition_met' => $conditionMet ? 1 : 0,
            ':signature' => $signature,
            ':id' => $id,
        ]);
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $columns = $this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
        foreach ($columns as $existing) {
            if (($existing['name'] ?? '') === $column) {
                return;
            }
        }
        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name");
        $stmt->execute([':name' => $table]);
        return (bool)$stmt->fetchColumn();
    }
}
