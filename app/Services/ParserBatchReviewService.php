<?php
declare(strict_types=1);

namespace LittyWatch\Services;

use LittyWatch\Market\OfferLifecycleService;
use LittyWatch\Market\StructuredOfferWriter;
use LittyWatch\Market\VariantNormalizer;
use LittyWatch\Market\MarketQualityService;
use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\DynamicKnowledge;
use LittyWatch\Parser\MessageClassifier;
use LittyWatch\Parser\ParserEngine;
use LittyWatch\Repositories\ParserKnowledgeRepository;
use LittyWatch\Repositories\ParserReviewRepository;
use PDO;
use Throwable;

final class ParserBatchReviewService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ParserReviewRepository $reviews,
    ) {}

    /**
     * @return array{
     *   checked:int,parsed:int,excluded:int,review:int,failed:int,
     *   next_cursor:int,done:bool,remaining:int
     * }
     */
    public function process(int $cursor = 0, int $limit = 150): array
    {
        $limit = max(10, min(300, $limit));

        $statement = $this->pdo->prepare(
            "SELECT DISTINCT m.id,m.message
             FROM messages m
             LEFT JOIN structured_offers so ON so.message_id=m.id
             LEFT JOIN parser_reviews pr ON pr.structured_offer_id=so.id
             WHERE m.id > :cursor
               AND (
                    COALESCE(m.parser_status,'review')='review'
                    OR pr.review_status='pending'
                    OR so.quality_status='review'
                    OR so.confidence < 0.85
               )
             ORDER BY m.id
             LIMIT :limit"
        );
        $statement->bindValue(':cursor', $cursor, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Phase 2U: re-review must always build a fresh parser from the files that
        // are currently deployed. Do not route this maintenance path through the
        // global parserV2() singleton: that made production re-review harder to
        // reason about and could leave it out of sync with direct parser tests.
        clearstatcache(true);
        $dataDir = dirname(__DIR__) . '/Data';
        $parser = new ParserEngine(new Catalog($dataDir, $this->pdo));
        $writer = new StructuredOfferWriter(
            $this->pdo,
            $parser,
            new VariantNormalizer(),
            null
        );
        $lifecycle = new OfferLifecycleService($this->pdo);
        $knowledgeRepository = new ParserKnowledgeRepository($this->pdo);
        $knowledgeRepository->install();
        $classifier = new MessageClassifier(
            new DynamicKnowledge($knowledgeRepository)
        );

        $result = [
            'checked' => 0,
            'parsed' => 0,
            'excluded' => 0,
            'review' => 0,
            'failed' => 0,
            'next_cursor' => $cursor,
            'done' => false,
            'remaining' => 0,
            'failure_samples' => [],
            'parser_release' => $this->parserRelease(),
        ];

        foreach ($rows as $row) {
            $messageId = (int)$row['id'];
            $message = (string)$row['message'];
            $result['next_cursor'] = $messageId;
            $result['checked']++;

            try {
                $this->pdo->beginTransaction();

                $deleteReviews = $this->pdo->prepare(
                    "DELETE FROM parser_reviews
                     WHERE structured_offer_id IN (
                         SELECT id FROM structured_offers WHERE message_id=?
                     )"
                );
                $deleteReviews->execute([$messageId]);

                $classification = $classifier->classify($message);
                if ($classification['kind'] !== 'market') {
                    $this->pdo->prepare("DELETE FROM offers WHERE message_id=?")->execute([$messageId]);
                    $this->pdo->prepare("DELETE FROM structured_offers WHERE message_id=?")->execute([$messageId]);

                    $summary = match ($classification['kind']) {
                        'service' => 'Serviceadvertentie uitgesloten',
                        'character_name_sale' => 'Naamverkoop uitgesloten',
                        'guild_advertisement' => 'Guildadvertentie uitgesloten',
                        'price_check' => 'Prijscheck uitgesloten',
                        default => 'Noise/contactregel uitgesloten',
                    };
                    $this->setMessageStatus($messageId, 'excluded', $summary, 0);
                    $result['excluded']++;
                    $this->pdo->commit();
                    continue;
                }

                $legacyCount = saveOffers($messageId, $message);
                $writer->parseMessage($messageId, $message, true);

                $quality = $this->qualityForMessage($messageId);
                if ($quality['review'] > 0 || $quality['accepted'] === 0) {
                    $summary = $quality['accepted'] > 0
                        ? $quality['accepted'] . ' herkend, ' . $quality['review'] . ' controle nodig'
                        : 'Niet betrouwbaar herkend · controle nodig';
                    $this->setMessageStatus($messageId, 'review', $summary, $legacyCount);
                    $result['review']++;
                } else {
                    $summary = $quality['accepted'] . ' aanbieding'
                        . ($quality['accepted'] === 1 ? '' : 'en')
                        . ' herkend';
                    $this->setMessageStatus($messageId, 'parsed', $summary, $legacyCount);
                    $result['parsed']++;
                }

                $this->pdo->commit();
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $result['failed']++;
                $failureText = 'Bericht ' . $messageId . ': '
                    . $exception->getMessage();

                if (count($result['failure_samples']) < 5) {
                    $result['failure_samples'][] = $failureText;
                }

                error_log('Batch parser review failed for ' . $failureText);
            }
        }

        if ($result['checked'] > 0 && $result['failed'] < $result['checked']) {
            try {
                $lifecycle->rebuild();
                (new MarketQualityService($this->pdo))->rebuildAll();
            } catch (Throwable $exception) {
                $failureText = 'Lifecycle rebuild: ' . $exception->getMessage();
                if (count($result['failure_samples']) < 5) {
                    $result['failure_samples'][] = $failureText;
                }
                error_log($failureText);
            }
        }

        $this->reviews->seedPending();
        $result['remaining'] = $this->remainingAfter($result['next_cursor']);
        $result['done'] = count($rows) < $limit || $result['remaining'] === 0;

        return $result;
    }


    private function parserRelease(): string
    {
        $path = dirname(__DIR__) . '/Data/parser-release.json';
        if (!is_file($path)) return 'unknown';
        try {
            $data = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            return trim((string)($data['release'] ?? 'unknown')) ?: 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /** @return array{accepted:int,review:int} */
    private function qualityForMessage(int $messageId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                SUM(CASE WHEN quality_status='accepted' AND confidence>=0.85 THEN 1 ELSE 0 END) accepted,
                SUM(CASE WHEN quality_status='review' OR confidence<0.85 THEN 1 ELSE 0 END) review
             FROM structured_offers
             WHERE message_id=?"
        );
        $statement->execute([$messageId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'accepted' => (int)($row['accepted'] ?? 0),
            'review' => (int)($row['review'] ?? 0),
        ];
    }

    private function setMessageStatus(
        int $messageId,
        string $status,
        string $summary,
        int $offerCount
    ): void {
        $statement = $this->pdo->prepare(
            "UPDATE messages
             SET parser_status=?,parser_summary=?,parser_offer_count=?
             WHERE id=?"
        );
        $statement->execute([$status, $summary, $offerCount, $messageId]);
    }

    private function remainingAfter(int $cursor): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT m.id)
             FROM messages m
             LEFT JOIN structured_offers so ON so.message_id=m.id
             LEFT JOIN parser_reviews pr ON pr.structured_offer_id=so.id
             WHERE m.id > :cursor
               AND (
                    COALESCE(m.parser_status,'review')='review'
                    OR pr.review_status='pending'
                    OR so.quality_status='review'
                    OR so.confidence < 0.85
               )"
        );
        $statement->execute([':cursor' => $cursor]);
        return (int)$statement->fetchColumn();
    }
}
