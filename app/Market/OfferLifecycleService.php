<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use PDO;

final class OfferLifecycleService
{
    private readonly int $expiryHours;

    public function __construct(private readonly PDO $pdo, ?int $expiryHours = null)
    {
        if ($expiryHours === null) {
            $cfg = require dirname(__DIR__, 2) . '/config/retention.php';
            $expiryHours = (int)($cfg['active_offer_hours'] ?? 48);
        }
        $this->expiryHours = max(1, $expiryHours);
    }

    public function expiryHours(): int
    {
        return $this->expiryHours;
    }

    /**
     * Rebuild lifecycle identity/deduplication and expiry state.
     *
     * When a message id is supplied only that message is reset first, but the
     * accepted offer set is still evaluated so a newly posted offer can
     * supersede an older listing from the same player/variant.
     */
    public function rebuild(?int $messageId = null): array
    {
        // LITTYWATCH_PHASE7D4_LIVE_TARGETED_DEDUP
        // Live collector calls should not scan/rewrite the entire market on
        // every new message. A short targeted transaction dramatically lowers
        // SQLite lock contention and still supersedes every older active copy
        // for the same player + trade direction + canonical market variant.
        if ($messageId !== null) {
            return $this->rebuildMessage($messageId);
        }

        return $this->rebuildAll();
    }

    private function rebuildMessage(int $messageId): array
    {
        return $this->withBusyRetry(function () use ($messageId): array {
            $this->pdo->beginTransaction();
            try {
                $msg = $this->pdo->prepare('SELECT player FROM messages WHERE id=?');
                $msg->execute([$messageId]);
                $player = $msg->fetchColumn();
                if ($player === false) {
                    $this->pdo->commit();
                    return ['active' => 0, 'superseded' => 0, 'expired' => 0];
                }

                // Reset only the freshly parsed rows. Older accepted rows keep
                // their current state until an identity from this message is
                // explicitly reconciled below.
                $reset = $this->pdo->prepare("UPDATE structured_offers SET lifecycle_status=CASE WHEN quality_status='accepted' THEN 'active' ELSE 'rejected' END, superseded_by=NULL, lifecycle_updated_at=datetime('now') WHERE message_id=?");
                $reset->execute([$messageId]);

                $offers = $this->pdo->prepare(<<<'SQL'
SELECT id, trade_type, item_key, normalized_market_key, requirement,
       attribute_key, is_oldschool, is_inscribable
FROM structured_offers
WHERE message_id=? AND quality_status='accepted'
ORDER BY id DESC
SQL);
                $offers->execute([$messageId]);
                $fresh = $offers->fetchAll(PDO::FETCH_ASSOC);

                $identities = [];
                foreach ($fresh as $row) {
                    $identity = $this->variantIdentity($row);
                    $key = mb_strtolower(trim((string)$row['trade_type'])) . '|' . $identity;
                    $identities[$key] = [
                        'trade_type' => (string)$row['trade_type'],
                        'identity' => $identity,
                    ];
                }

                $active = 0;
                $superseded = 0;
                $expired = 0;
                foreach ($identities as $spec) {
                    $rows = $this->rowsForPlayerIdentity((string)$player, $spec['trade_type'], $spec['identity']);
                    if ($rows === []) {
                        continue;
                    }

                    $winner = $rows[0];
                    $winnerId = (int)$winner['id'];
                    $isExpired = $this->isExpired((string)$winner['posted_at']);
                    $winnerStatus = $isExpired ? 'expired' : 'active';

                    $u = $this->pdo->prepare("UPDATE structured_offers SET lifecycle_status=?, superseded_by=?, lifecycle_updated_at=datetime('now') WHERE id=?");
                    $u->execute([$winnerStatus, null, $winnerId]);
                    if ($isExpired) {
                        $expired++;
                    } else {
                        $active++;
                    }

                    foreach (array_slice($rows, 1) as $old) {
                        $u->execute(['superseded', $winnerId, (int)$old['id']]);
                        $superseded++;
                    }
                }

                $this->pdo->commit();
                return ['active' => $active, 'superseded' => $superseded, 'expired' => $expired];
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        });
    }

    private function rebuildAll(): array
    {
        return $this->withBusyRetry(function (): array {
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec("UPDATE structured_offers SET lifecycle_status='active', superseded_by=NULL, lifecycle_updated_at=datetime('now') WHERE quality_status='accepted'");
                $this->pdo->exec("UPDATE structured_offers SET lifecycle_status='rejected', superseded_by=NULL, lifecycle_updated_at=datetime('now') WHERE quality_status<>'accepted'");

                $rows = $this->pdo->query("SELECT so.id,so.trade_type,so.item_key,so.normalized_market_key,so.requirement,so.attribute_key,so.is_oldschool,so.is_inscribable,m.player,m.posted_at,m.id message_id FROM structured_offers so JOIN messages m ON m.id=so.message_id WHERE so.quality_status='accepted' ORDER BY datetime(m.posted_at) DESC,m.id DESC,so.id DESC")->fetchAll(PDO::FETCH_ASSOC);
                $seen = [];
                $superseded = 0;
                $expired = 0;
                $active = 0;
                $update = $this->pdo->prepare("UPDATE structured_offers SET lifecycle_status=?,superseded_by=?,lifecycle_updated_at=datetime('now') WHERE id=?");

                foreach ($rows as $row) {
                    $key = implode('|', [
                        mb_strtolower(trim((string)$row['player'])),
                        mb_strtolower(trim((string)$row['trade_type'])),
                        $this->variantIdentity($row),
                    ]);

                    if (isset($seen[$key])) {
                        $update->execute(['superseded', $seen[$key], (int)$row['id']]);
                        $superseded++;
                        continue;
                    }

                    $seen[$key] = (int)$row['id'];
                    if ($this->isExpired((string)$row['posted_at'])) {
                        $update->execute(['expired', null, (int)$row['id']]);
                        $expired++;
                    } else {
                        $active++;
                    }
                }

                $this->pdo->commit();
                return ['active' => $active, 'superseded' => $superseded, 'expired' => $expired];
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        });
    }

    private function rowsForPlayerIdentity(string $player, string $tradeType, string $identity): array
    {
        // Pull only this player's accepted rows. Identity comparison stays in
        // PHP so legacy pre-7D2 rows can still use the canonical fallback.
        $stmt = $this->pdo->prepare(<<<'SQL'
SELECT so.id, so.trade_type, so.item_key, so.normalized_market_key,
       so.requirement, so.attribute_key, so.is_oldschool, so.is_inscribable,
       m.posted_at, m.id AS message_id
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE so.quality_status='accepted'
  AND lower(trim(m.player))=lower(trim(?))
  AND lower(trim(so.trade_type))=lower(trim(?))
ORDER BY datetime(m.posted_at) DESC, m.id DESC, so.id DESC
SQL);
        $stmt->execute([$player, $tradeType]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($this->variantIdentity($row) === $identity) {
                $out[] = $row;
            }
        }
        return $out;
    }

    private function variantIdentity(array $row): string
    {
        $canonicalItem = $this->key((string)($row['item_key'] ?? ''));
        $normalizedMarket = trim((string)($row['normalized_market_key'] ?? ''));
        $normalizedPrefix = $normalizedMarket === '' ? '' : explode('|', $normalizedMarket, 2)[0];
        $normalizedPrefix = $this->key($normalizedPrefix);

        if ($normalizedMarket !== '' && $normalizedPrefix === $canonicalItem) {
            return mb_strtolower($normalizedMarket);
        }

        return implode('|', [
            $canonicalItem,
            ($row['requirement'] ?? null) === null ? '' : 'q:' . (string)$row['requirement'],
            mb_strtolower(trim((string)($row['attribute_key'] ?? ''))),
            (string)((int)($row['is_oldschool'] ?? 0)),
            (string)((int)($row['is_inscribable'] ?? 0)),
        ]);
    }

    private function withBusyRetry(callable $fn): array
    {
        $last = null;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return $fn();
            } catch (\PDOException $e) {
                $last = $e;
                if (!str_contains(mb_strtolower($e->getMessage()), 'database is locked') || $attempt === 5) {
                    throw $e;
                }
                usleep(150000 * $attempt);
            }
        }
        throw $last ?? new \RuntimeException('Lifecycle retry failed.');
    }

    /**
     * LITTYWATCH_PHASE7D5_ACTIVE_DUPLICATE_HEAL
     * Final safety net after a collector batch. Even if an earlier targeted
     * lifecycle reconciliation was skipped or interrupted, there may never be
     * more than one active accepted row for player + direction + variant.
     *
     * Only currently-active accepted rows are scanned, keeping this much
     * cheaper than a full lifecycle rebuild. History remains intact: older
     * rows become superseded and point at the newest active winner.
     */
    public function healActiveDuplicates(): array
    {
        return $this->withBusyRetry(function (): array {
            $this->pdo->beginTransaction();
            try {
                $rows = $this->pdo->query(<<<'SQL'
SELECT so.id, so.trade_type, so.item_key, so.normalized_market_key,
       so.requirement, so.attribute_key, so.is_oldschool, so.is_inscribable,
       m.player, m.posted_at, m.id AS message_id
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE so.quality_status='accepted'
  AND so.lifecycle_status='active'
ORDER BY datetime(m.posted_at) DESC, m.id DESC, so.id DESC
SQL)->fetchAll(PDO::FETCH_ASSOC);

                $seen = [];
                $superseded = 0;
                $update = $this->pdo->prepare("UPDATE structured_offers SET lifecycle_status='superseded', superseded_by=?, lifecycle_updated_at=datetime('now') WHERE id=? AND lifecycle_status='active'");

                foreach ($rows as $row) {
                    $key = implode('|', [
                        mb_strtolower(trim((string)$row['player'])),
                        mb_strtolower(trim((string)$row['trade_type'])),
                        $this->variantIdentity($row),
                    ]);

                    if (!isset($seen[$key])) {
                        $seen[$key] = (int)$row['id'];
                        continue;
                    }

                    $update->execute([$seen[$key], (int)$row['id']]);
                    $superseded += $update->rowCount();
                }

                $this->pdo->commit();
                return [
                    'active_identities' => count($seen),
                    'superseded' => $superseded,
                ];
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        });
    }

    /**
     * Cheap periodic expiry pass used by the collector even when no new
     * Kamadan messages were inserted. Superseded/rejected rows are untouched.
     */
    public function expireStaleOffers(): int
    {
        $cutoff = (new \DateTimeImmutable('-' . $this->expiryHours . ' hours'))->format(DATE_ATOM);
        $stmt = $this->pdo->prepare(<<<'SQL'
UPDATE structured_offers
SET lifecycle_status='expired',
    superseded_by=NULL,
    lifecycle_updated_at=datetime('now')
WHERE quality_status='accepted'
  AND COALESCE(lifecycle_status,'active')='active'
  AND message_id IN (
      SELECT id FROM messages WHERE datetime(posted_at) < datetime(:cutoff)
  )
SQL);
        $stmt->execute([':cutoff' => $cutoff]);
        return $stmt->rowCount();
    }

    private function key(string $value): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value)), '_');
    }

    private function isExpired(string $postedAt): bool
    {
        try {
            $date = new \DateTimeImmutable($postedAt);
            $year = (int)$date->format('Y');
            if ($year < 2005 || $year > 2100) {
                return false;
            }
            return $date < new \DateTimeImmutable('-' . $this->expiryHours . ' hours');
        } catch (\Throwable) {
            return false;
        }
    }
}
