<?php

declare(strict_types=1);

namespace LittyWatch\Snapshots;

use PDO;

final class SnapshotService
{
    public function __construct(private PDO $pdo, private MarketStats $stats)
    {
    }

    public function captureAll(int $limit = 250): int
    {
        $markets = $this->stats->activeMarkets($limit);
        $inserted = 0;

        $stmt = $this->pdo->prepare(<<<SQL
INSERT INTO market_snapshots (
    market_key, best_wtb_ecto, best_wts_ecto,
    median_wtb_ecto, median_wts_ecto,
    active_offers, unique_traders, captured_at
) VALUES (
    :market_key, :best_wtb, :best_wts,
    :median_wtb, :median_wts,
    :active_offers, :unique_traders, :captured_at
)
SQL);

        $now = (new \DateTimeImmutable('now'))->format(DATE_ATOM);
        $this->pdo->beginTransaction();
        try {
            foreach ($markets as $market) {
                $summary = $this->stats->summarize((string)$market['market_key']);
                if ($summary === null) {
                    continue;
                }
                $stmt->execute([
                    ':market_key' => $summary['market_key'],
                    ':best_wtb' => $summary['best_wtb_ecto'],
                    ':best_wts' => $summary['best_wts_ecto'],
                    ':median_wtb' => $summary['median_wtb_ecto'],
                    ':median_wts' => $summary['median_wts_ecto'],
                    ':active_offers' => $summary['active_offers'],
                    ':unique_traders' => $summary['unique_traders'],
                    ':captured_at' => $now,
                ]);
                $inserted++;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $inserted;
    }
}
