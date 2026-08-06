<?php
declare(strict_types=1);

namespace LittyWatch\Services;

use LittyWatch\Market\OfferLifecycleService;
use LittyWatch\Market\StructuredOfferWriter;
use LittyWatch\Market\VariantNormalizer;
use LittyWatch\Parser\DynamicKnowledge;
use LittyWatch\Parser\MessageClassifier;
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

        $writer = new StructuredOfferWriter(
            $this->pdo,
            parserV2(),
            new VariantNormalizer(),
            new OfferLifecycleService($this->pdo)
        );
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
                error_log(
                    'Batch parser review failed for message '
                    . $messageId . ': ' . $exception->getMessage()
                );
            }
        }

        $this->reviews->seedPending();
        $result['remaining'] = $this->remainingAfter($result['next_cursor']);
        $result['done'] = count($rows) < $limit || $result['remaining'] === 0;

        return $result;
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
