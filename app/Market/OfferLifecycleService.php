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

            $rows = $this->pdo->query("SELECT so.id,so.trade_type,so.item_key,so.requirement,so.attribute_key,so.is_oldschool,so.is_inscribable,m.player,m.posted_at,m.id message_id FROM structured_offers so JOIN messages m ON m.id=so.message_id WHERE so.quality_status='accepted' ORDER BY datetime(m.posted_at) DESC,m.id DESC,so.id DESC")->fetchAll();
            $seen = [];
            $superseded = 0;
            $expired = 0;
            $update = $this->pdo->prepare("UPDATE structured_offers SET lifecycle_status=?,superseded_by=?,lifecycle_updated_at=datetime('now') WHERE id=?");
            foreach ($rows as $row) {
                $key = implode('|', [
                    mb_strtolower(trim((string)$row['player'])),
                    (string)$row['trade_type'],
                    mb_strtolower(trim((string)$row['item_key'])),
                    $row['requirement'] === null ? '' : (string)$row['requirement'],
                    mb_strtolower(trim((string)($row['attribute_key'] ?? ''))),
                    (string)((int)($row['is_oldschool'] ?? 0)),
                    (string)((int)($row['is_inscribable'] ?? 0)),
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
                }
            }
            $this->pdo->commit();
            return ['active' => count($seen) - $expired, 'superseded' => $superseded, 'expired' => $expired];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function isExpired(string $postedAt): bool
    {
        try {
            $date = new \DateTimeImmutable($postedAt);
            $year = (int)$date->format('Y');
            if ($year < 2005 || $year > 2100) return false;
            return $date < new \DateTimeImmutable('-' . $this->expiryHours . ' hours');
        } catch (\Throwable) {
            return false;
        }
    }
}
