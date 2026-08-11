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
        $this->pdo->beginTransaction();
        try {
            if ($messageId === null) {
                $this->pdo->exec("UPDATE structured_offers SET lifecycle_status='active', superseded_by=NULL, lifecycle_updated_at=datetime('now') WHERE quality_status='accepted'");
                $this->pdo->exec("UPDATE structured_offers SET lifecycle_status='rejected', superseded_by=NULL, lifecycle_updated_at=datetime('now') WHERE quality_status<>'accepted'");
            } else {
                $s = $this->pdo->prepare("UPDATE structured_offers SET lifecycle_status=CASE WHEN quality_status='accepted' THEN 'active' ELSE 'rejected' END, superseded_by=NULL, lifecycle_updated_at=datetime('now') WHERE message_id=?");
                $s->execute([$messageId]);
            }

            $rows = $this->pdo->query("SELECT so.id,so.trade_type,so.item_key,so.normalized_market_key,so.requirement,so.attribute_key,so.is_oldschool,so.is_inscribable,so.lifecycle_status,m.player,m.posted_at,m.id message_id FROM structured_offers so JOIN messages m ON m.id=so.message_id WHERE so.quality_status='accepted' ORDER BY datetime(m.posted_at) DESC,m.id DESC,so.id DESC")->fetchAll();
            $seen = [];
            $superseded = 0;
            $expired = 0;
            $update = $this->pdo->prepare("UPDATE structured_offers SET lifecycle_status=?,superseded_by=?,lifecycle_updated_at=datetime('now') WHERE id=?");

            foreach ($rows as $row) {
                // LITTYWATCH_PHASE7D2_CANONICAL_LIVE_IDENTITY
                // New rows have a canonical normalized_market_key. Old rows may
                // still contain a generic pre-catalog key, so only trust the
                // normalized key when it begins with the canonical item key.
                $canonicalItem = $this->key((string)$row['item_key']);
                $normalizedMarket = trim((string)($row['normalized_market_key'] ?? ''));
                $normalizedPrefix = $normalizedMarket === '' ? '' : explode('|', $normalizedMarket, 2)[0];
                $normalizedPrefix = $this->key($normalizedPrefix);

                if ($normalizedMarket !== '' && $normalizedPrefix === $canonicalItem) {
                    $variantIdentity = mb_strtolower($normalizedMarket);
                } else {
                    $variantIdentity = implode('|', [
                        $canonicalItem,
                        $row['requirement'] === null ? '' : 'q:' . (string)$row['requirement'],
                        mb_strtolower(trim((string)($row['attribute_key'] ?? ''))),
                        (string)((int)($row['is_oldschool'] ?? 0)),
                        (string)((int)($row['is_inscribable'] ?? 0)),
                    ]);
                }

                $key = implode('|', [
                    mb_strtolower(trim((string)$row['player'])),
                    mb_strtolower(trim((string)$row['trade_type'])),
                    $variantIdentity,
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
                } elseif (($row['lifecycle_status'] ?? 'active') !== 'active') {
                    // Only relevant for partial rebuilds: the newest accepted row for
                    // a listing identity must remain active when it is not expired.
                    $update->execute(['active', null, (int)$row['id']]);
                }
            }

            $this->pdo->commit();
            return [
                'active' => count($seen) - $expired,
                'superseded' => $superseded,
                'expired' => $expired,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
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
