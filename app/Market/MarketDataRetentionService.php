<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use PDO;

final class MarketDataRetentionService
{
    private array $config;

    public function __construct(private readonly PDO $pdo, ?array $config = null)
    {
        $this->config = $config ?? require dirname(__DIR__, 2) . '/config/retention.php';
    }

    public function report(): array
    {
        $days = max(1, (int)($this->config['message_retention_days'] ?? 21));
        $maxMessages = max(1000, (int)($this->config['max_messages'] ?? 75000));
        $historyCap = max(10, (int)($this->config['max_historical_offers_per_market'] ?? 250));
        $cutoff = (new \DateTimeImmutable('-' . $days . ' days'))->format(DATE_ATOM);

        $totalMessages = (int)$this->pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
        $totalStructured = (int)$this->pdo->query('SELECT COUNT(*) FROM structured_offers')->fetchColumn();

        $s = $this->pdo->prepare('SELECT COUNT(*) FROM messages WHERE datetime(posted_at) < datetime(?)');
        $s->execute([$cutoff]);
        $olderThanRetention = (int)$s->fetchColumn();

        $protectedOld = $this->countProtectedMessagesBefore($cutoff);
        $eligibleOld = max(0, $olderThanRetention - $protectedOld);
        $overHardCap = max(0, $totalMessages - $maxMessages);

        return [
            'messages' => $totalMessages,
            'structured_offers' => $totalStructured,
            'retention_days' => $days,
            'cutoff' => $cutoff,
            'older_than_retention' => $olderThanRetention,
            'protected_reviewed_messages' => $protectedOld,
            'eligible_old_messages' => $eligibleOld,
            'max_messages' => $maxMessages,
            'over_hard_cap' => $overHardCap,
            'history_cap_per_market' => $historyCap,
            'historical_offer_overflow' => $this->countHistoricalOverflow($historyCap),
        ];
    }

    public function prune(bool $vacuum = false): array
    {
        $before = $this->report();
        $days = (int)$before['retention_days'];
        $maxMessages = (int)$before['max_messages'];
        $historyCap = (int)$before['history_cap_per_market'];
        $cutoff = (string)$before['cutoff'];

        $this->pdo->beginTransaction();
        try {
            // 1. Begrens overtollige historische offer-rows per marktvariant.
            $historicalDeleted = $this->deleteHistoricalOverflow($historyCap);

            // 2. Verwijder oude ruwe berichten. Handmatig afgeronde reviews blijven
            // beschermd zodat trainings-/kenniswerk niet stilletjes verdwijnt.
            $oldStmt = $this->pdo->prepare(<<<'SQL'
DELETE FROM messages
WHERE datetime(posted_at) < datetime(:cutoff)
  AND NOT EXISTS (
      SELECT 1
      FROM structured_offers so
      JOIN parser_reviews pr ON pr.structured_offer_id = so.id
      WHERE so.message_id = messages.id
        AND pr.review_status IN ('approved','corrected','rejected')
  )
SQL);
            $oldStmt->execute([':cutoff' => $cutoff]);
            $oldMessagesDeleted = $oldStmt->rowCount();

            // 3. Harde vangrail: als de bron in 21 dagen uitzonderlijk veel data levert,
            // verwijder dan oudste niet-beschermde berichten tot het plafond.
            $count = (int)$this->pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
            $capDeleted = 0;
            $need = max(0, $count - $maxMessages);
            if ($need > 0) {
                $cap = $this->pdo->prepare(<<<'SQL'
DELETE FROM messages
WHERE id IN (
    SELECT m.id
    FROM messages m
    WHERE NOT EXISTS (
        SELECT 1
        FROM structured_offers so
        JOIN parser_reviews pr ON pr.structured_offer_id = so.id
        WHERE so.message_id = m.id
          AND pr.review_status IN ('approved','corrected','rejected')
    )
    ORDER BY datetime(m.posted_at) ASC, m.id ASC
    LIMIT :limit
)
SQL);
                $cap->bindValue(':limit', $need, PDO::PARAM_INT);
                $cap->execute();
                $capDeleted = $cap->rowCount();
            }

            $this->setSetting('retention_last_run', date(DATE_ATOM));
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        if ($vacuum) {
            $this->pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $this->pdo->exec('VACUUM');
        }

        return [
            'historical_offers_deleted' => $historicalDeleted,
            'old_messages_deleted' => $oldMessagesDeleted,
            'hard_cap_messages_deleted' => $capDeleted,
            'vacuum' => $vacuum,
            'before' => $before,
            'after' => $this->report(),
        ];
    }

    public function pruneIfDue(): ?array
    {
        $hours = max(1, (int)($this->config['auto_prune_interval_hours'] ?? 24));
        $last = $this->getSetting('retention_last_run');
        if ($last !== null) {
            $ts = strtotime($last);
            if ($ts !== false && $ts > time() - ($hours * 3600)) return null;
        }
        return $this->prune(false);
    }

    private function countProtectedMessagesBefore(string $cutoff): int
    {
        $s = $this->pdo->prepare(<<<'SQL'
SELECT COUNT(DISTINCT m.id)
FROM messages m
JOIN structured_offers so ON so.message_id=m.id
JOIN parser_reviews pr ON pr.structured_offer_id=so.id
WHERE datetime(m.posted_at) < datetime(?)
  AND pr.review_status IN ('approved','corrected','rejected')
SQL);
        $s->execute([$cutoff]);
        return (int)$s->fetchColumn();
    }

    private function countHistoricalOverflow(int $cap): int
    {
        $sql = <<<SQL
WITH ranked AS (
    SELECT so.id,
           ROW_NUMBER() OVER (
               PARTITION BY COALESCE(NULLIF(so.normalized_market_key,''),so.market_key), so.trade_type
               ORDER BY datetime(m.posted_at) DESC, so.id DESC
           ) rn
    FROM structured_offers so
    JOIN messages m ON m.id=so.message_id
    WHERE COALESCE(so.lifecycle_status,'active') <> 'active'
      AND NOT EXISTS (
          SELECT 1 FROM parser_reviews pr
          WHERE pr.structured_offer_id=so.id
            AND pr.review_status IN ('approved','corrected','rejected')
      )
)
SELECT COUNT(*) FROM ranked WHERE rn > {$cap}
SQL;
        return (int)$this->pdo->query($sql)->fetchColumn();
    }

    private function deleteHistoricalOverflow(int $cap): int
    {
        $sql = <<<SQL
DELETE FROM structured_offers
WHERE id IN (
    WITH ranked AS (
        SELECT so.id,
               ROW_NUMBER() OVER (
                   PARTITION BY COALESCE(NULLIF(so.normalized_market_key,''),so.market_key), so.trade_type
                   ORDER BY datetime(m.posted_at) DESC, so.id DESC
               ) rn
        FROM structured_offers so
        JOIN messages m ON m.id=so.message_id
        WHERE COALESCE(so.lifecycle_status,'active') <> 'active'
          AND NOT EXISTS (
              SELECT 1 FROM parser_reviews pr
              WHERE pr.structured_offer_id=so.id
                AND pr.review_status IN ('approved','corrected','rejected')
          )
    )
    SELECT id FROM ranked WHERE rn > {$cap}
)
SQL;
        return $this->pdo->exec($sql);
    }

    private function getSetting(string $key): ?string
    {
        $s = $this->pdo->prepare('SELECT value FROM settings WHERE key=?');
        $s->execute([$key]);
        $v = $s->fetchColumn();
        return $v === false ? null : (string)$v;
    }

    private function setSetting(string $key, string $value): void
    {
        $s = $this->pdo->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
        $s->execute([$key,$value]);
    }
}
