<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use PDO;

final class OfferLifecycleService
{
    public function __construct(private readonly PDO $pdo, private readonly int $expiryDays = 14) {}

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

            $rows = $this->pdo->query("SELECT so.id,so.trade_type,COALESCE(NULLIF(so.normalized_market_key,''),so.market_key) market_key,m.player,m.posted_at,m.id message_id FROM structured_offers so JOIN messages m ON m.id=so.message_id WHERE so.quality_status='accepted' ORDER BY m.id DESC,so.id DESC")->fetchAll();
            $seen = [];
            $superseded = 0;
            $expired = 0;
            $update = $this->pdo->prepare("UPDATE structured_offers SET lifecycle_status=?,superseded_by=?,lifecycle_updated_at=datetime('now') WHERE id=?");
            foreach ($rows as $row) {
                $key = mb_strtolower(trim((string)$row['player'])) . '|' . $row['trade_type'] . '|' . $row['market_key'];
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
            return $date < new \DateTimeImmutable('-' . $this->expiryDays . ' days');
        } catch (\Throwable) {
            return false;
        }
    }
}
