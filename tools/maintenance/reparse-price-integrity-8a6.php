<?php
declare(strict_types=1);

/**
 * LittyWatch Phase 8A targeted Price Integrity reparse.
 *
 * Reparse ONLY messages whose text can be affected by Phase 8A price/quantity
 * semantics. This avoids reparsing the complete historic Kamadan dataset.
 *
 * Usage:
 *   php tools/maintenance/reparse-price-integrity.php --dry-run
 *   php tools/maintenance/reparse-price-integrity.php
 *   php tools/maintenance/reparse-price-integrity.php --limit=500
 *   php tools/maintenance/reparse-price-integrity.php --batch-size=100
 *
 * Safety:
 * - CLI only.
 * - Takes the same exclusive kamadan-collector.lock used by collector/reparse.
 * - Does not touch messages outside the 8A candidate set.
 * - StructuredOfferWriter remains the only parser/write path.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Dit onderhoudsscript mag alleen via CLI worden uitgevoerd.\n");
    exit(1);
}

@set_time_limit(0);
@ini_set('max_execution_time', '0');

$options = getopt('', ['dry-run', 'limit::', 'batch-size::']);
$dryRun = array_key_exists('dry-run', $options);
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : null;
$batchSize = isset($options['batch-size']) ? max(10, min(1000, (int)$options['batch-size'])) : 100;

$root = dirname(__DIR__, 2);
$storage = $root . '/storage';
if (!is_dir($storage) && !@mkdir($storage, 0775, true) && !is_dir($storage)) {
    fwrite(STDERR, "Kan storage-map niet aanmaken: {$storage}\n");
    exit(2);
}

$lockPath = $storage . '/kamadan-collector.lock';
$maintenanceLock = fopen($lockPath, 'c+');
if ($maintenanceLock === false) {
    fwrite(STDERR, "Kan maintenance-lock niet openen: {$lockPath}\n");
    exit(2);
}

if (!flock($maintenanceLock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Collector of andere maintenance/reparse draait nog.\n");
    fwrite(STDERR, "Stop die eerst; zodra dit script draait houdt het zelf de collector tegen.\n");
    fclose($maintenanceLock);
    exit(3);
}

register_shutdown_function(static function () use ($maintenanceLock): void {
    @flock($maintenanceLock, LOCK_UN);
    @fclose($maintenanceLock);
});

require $root . '/bootstrap.php';

use LittyWatch\Market\MarketQualityService;
use LittyWatch\Market\OfferLifecycleService;
use LittyWatch\Market\StructuredOfferWriter;
use LittyWatch\Market\VariantNormalizer;
use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;
use LittyWatch\Repositories\ParserKnowledgeRepository;
use LittyWatch\Repositories\ParserReviewRepository;

$pdo = db();
installSchema();
$pdo->exec('PRAGMA busy_timeout = 30000');

$parser = new ParserEngine(new Catalog($root . '/app/Data', $pdo));
$writer = new StructuredOfferWriter($pdo, $parser, new VariantNormalizer(), null);
$lifecycle = new OfferLifecycleService($pdo);
$quality = new MarketQualityService($pdo);

/**
 * Return true only for messages whose parse result may be changed by 8A.
 *
 * The patterns intentionally target syntax, not item names alone:
 * - explicit quantity + commodity/item + total money
 * - "N item for M currency"
 * - explicit leading quantities for quantity-aware commodities
 * - "N item for M currency"
 * - shared trailing list prices
 * - compact Elite Tome lists with "(N)"
 */
$isCandidate = static function (string $message): bool {
    $m = mb_strtolower($message);
    $money = '(?:a|ambr(?:ace)?s?|armbraces?|e|ecto(?:s)?|k|plat(?:inum)?)';
    $item = '(?:gott?s?|nickgifts?|nick\s+gifts?|gift(?:s)?\s+of\s+the\s+travell?er)';

    // Phase 8A.6 only: quantity concatenated directly with the item name.
    // Examples: 5NickGifts 10e, 5GoTT 12e.
    if (preg_match(
        '/(?<![a-z0-9])\d+'.$item.'\b[^\r\n|;,]{0,40}?\d+(?:[.,]\d+)?\s*'.$money.'\b/iu',
        $m
    )) {
        return true;
    }

    // Compact x-prefix: 5xGoTT 12e / 5xGift of the Traveler 10e.
    if (preg_match(
        '/(?<![a-z0-9])\d+\s*x\s*'.$item.'\b[^\r\n|;,]{0,40}?\d+(?:[.,]\d+)?\s*'.$money.'\b/iu',
        $m
    )) {
        return true;
    }

    // Quantity after the item: GoTT x5 12e / NickGifts x5 10e.
    if (preg_match(
        '/\b'.$item.'\b\s*x\s*\d+[^\r\n|;,]{0,30}?\d+(?:[.,]\d+)?\s*'.$money.'\b/iu',
        $m
    )) {
        return true;
    }

    return false;
};

// Scanning message text is cheap; parsing is the expensive operation.
// Fetch in chunks so memory remains bounded even when message history grows.
$candidateIds = [];
$lastScanId = 0;
$scanBatch = 5000;
$scanned = 0;

while (true) {
    $stmt = $pdo->prepare(
        'SELECT id, message
         FROM messages
         WHERE id > :last_id
         ORDER BY id
         LIMIT :limit'
    );
    $stmt->bindValue(':last_id', $lastScanId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $scanBatch, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) break;

    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $lastScanId = $id;
        $scanned++;
        if ($isCandidate((string)$row['message'])) {
            $candidateIds[] = $id;
            if ($limit !== null && count($candidateIds) >= $limit) break 2;
        }
    }
}

$totalCandidates = count($candidateIds);

fwrite(STDOUT, "LittyWatch Phase 8A.6 compacte quantity reparse\n");
fwrite(STDOUT, "Berichten gescand: {$scanned}\n");
fwrite(STDOUT, "8A.6 kandidaten:   {$totalCandidates}\n");
if ($limit !== null) fwrite(STDOUT, "Limiet actief:     {$limit}\n");
fwrite(STDOUT, "Batch size:        {$batchSize}\n");

if ($dryRun) {
    fwrite(STDOUT, "\nDRY RUN: niets gewijzigd.\n");

    if ($totalCandidates > 0) {
        fwrite(STDOUT, "\nVoorbeeld kandidaten:\n");
        $sampleIds = array_slice($candidateIds, 0, 20);
        $marks = implode(',', array_fill(0, count($sampleIds), '?'));
        $sample = $pdo->prepare("SELECT id, player, message FROM messages WHERE id IN ({$marks}) ORDER BY id");
        $sample->execute($sampleIds);
        foreach ($sample->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $text = preg_replace('/\s+/u', ' ', trim((string)$row['message'])) ?? '';
            if (mb_strlen($text) > 140) $text = mb_substr($text, 0, 137) . '...';
            fwrite(STDOUT, sprintf("  #%d %-20s %s\n", (int)$row['id'], mb_substr((string)$row['player'], 0, 20), $text));
        }
    }
    exit(0);
}

if ($totalCandidates === 0) {
    fwrite(STDOUT, "\nGeen kandidaten. Klaar.\n");
    exit(0);
}

$fetchMessage = $pdo->prepare('SELECT id, message FROM messages WHERE id=?');
$oldKeysStmt = $pdo->prepare('SELECT DISTINCT item_key FROM structured_offers WHERE message_id=?');
$newStatsStmt = $pdo->prepare(
    "SELECT
        COUNT(*) total,
        SUM(CASE WHEN quality_status='accepted' THEN 1 ELSE 0 END) accepted,
        SUM(CASE WHEN quality_status='review' THEN 1 ELSE 0 END) review,
        SUM(CASE WHEN quality_status='rejected' THEN 1 ELSE 0 END) rejected
     FROM structured_offers
     WHERE message_id=?"
);
$newKeysStmt = $pdo->prepare('SELECT DISTINCT item_key FROM structured_offers WHERE message_id=?');
$updateMessage = $pdo->prepare(
    'UPDATE messages
     SET parser_status=?, parser_summary=?, parser_offer_count=?
     WHERE id=?'
);

$processed = 0;
$created = 0;
$accepted = 0;
$review = 0;
$rejected = 0;
$affectedKeys = [];
$lifecycleResult = [];
$started = microtime(true);

/**
 * Retry a complete message parse on transient SQLite BUSY/LOCKED errors.
 * Roll back before each retry so no partial message transaction survives.
 */
$parseWithBusyRetry = static function (callable $fn, PDO $pdo, int $messageId, int $attempts = 20) {
    $delayUs = 100000;
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        try {
            return $fn();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();

            $msg = strtolower($e->getMessage());
            $busy = str_contains($msg, 'database is locked')
                || str_contains($msg, 'database is busy')
                || str_contains($msg, 'general error: 5');

            if (!$busy || $attempt === $attempts) throw $e;

            fwrite(
                STDOUT,
                sprintf(
                    "\nmessage #%d: database busy, retry %d/%d...\n",
                    $messageId,
                    $attempt,
                    $attempts
                )
            );

            usleep($delayUs);
            $delayUs = min(2000000, (int)($delayUs * 1.5));
        }
    }

    throw new RuntimeException("Retry uitgeput voor message #{$messageId}");
};


foreach (array_chunk($candidateIds, $batchSize) as $batchIndex => $batchIds) {
    $batchKeys = [];

    foreach ($batchIds as $messageId) {
        $fetchMessage->execute([$messageId]);
        $row = $fetchMessage->fetch(PDO::FETCH_ASSOC);
        $fetchMessage->closeCursor();
        if (!$row) continue;

        $message = (string)$row['message'];

        // Keep both previous and new item keys. A corrected parse can move an offer
        // from one market key to another; both groups then need price-quality refresh.
        $oldKeysStmt->execute([$messageId]);
        foreach ($oldKeysStmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
            $key = trim((string)$key);
            if ($key !== '') {
                $batchKeys[$key] = true;
                $affectedKeys[$key] = true;
            }
        }
        $oldKeysStmt->closeCursor();

        try {
            // Do NOT hold one transaction open across the full ParserEngine pass.
            // StructuredOfferWriter is replace/idempotent: if a transient lock hits,
            // retrying the whole message safely recreates its structured rows.
            $newCount = $parseWithBusyRetry(
                static fn() => $writer->parseMessage($messageId, $message, true),
                $pdo,
                $messageId
            );

            $newStatsStmt->execute([$messageId]);
            $stats = $newStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $newStatsStmt->closeCursor();

            $a = (int)($stats['accepted'] ?? 0);
            $r = (int)($stats['review'] ?? 0);
            $x = (int)($stats['rejected'] ?? 0);

            if ($r > 0 || $a === 0) {
                $status = 'review';
                $summary = $a > 0
                    ? "{$a} herkend, {$r} controle nodig"
                    : 'Niet betrouwbaar herkend · controle nodig';
            } else {
                $status = 'parsed';
                $summary = $a . ' aanbieding' . ($a === 1 ? '' : 'en') . ' herkend';
            }

            $parseWithBusyRetry(
                static function () use ($updateMessage, $status, $summary, $newCount, $messageId): int {
                    $updateMessage->execute([$status, $summary, $newCount, $messageId]);
                    return $updateMessage->rowCount();
                },
                $pdo,
                $messageId
            );

            $newKeysStmt->execute([$messageId]);
            foreach ($newKeysStmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
                $key = trim((string)$key);
                if ($key !== '') {
                    $batchKeys[$key] = true;
                    $affectedKeys[$key] = true;
                }
            }
            $newKeysStmt->closeCursor();

            $created += (int)$newCount;
            $accepted += $a;
            $review += $r;
            $rejected += $x;
            $processed++;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            fwrite(STDERR, "\nFOUT bij message #{$messageId}: {$e->getMessage()}\n");
            fwrite(STDERR, "Gestopt zonder overige kandidaten te wijzigen.\n");
            exit(10);
        }
    }

    $pct = $totalCandidates > 0 ? ($processed / $totalCandidates) * 100 : 100.0;
    $elapsed = max(0.001, microtime(true) - $started);
    $rate = $processed / $elapsed;
    fwrite(
        STDOUT,
        sprintf(
            "%d/%d (%.1f%%) | offers=%d | %.1f msg/s\n",
            $processed,
            $totalCandidates,
            $pct,
            $created,
            $rate
        )
    );
}


/**
 * Retry transient SQLite BUSY/LOCKED errors. Other LittyWatch cron jobs may
 * briefly touch the same database even while the collector lock is held.
 */
$withBusyRetry = static function (callable $fn, string $label, int $attempts = 12) {
    $delayUs = 250000;
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        try {
            return $fn();
        } catch (PDOException $e) {
            $msg = strtolower($e->getMessage());
            $busy = str_contains($msg, 'database is locked') || str_contains($msg, 'database is busy');
            if (!$busy || $attempt === $attempts) throw $e;
            fwrite(STDOUT, sprintf("%s: database busy, retry %d/%d...\n", $label, $attempt, $attempts));
            usleep($delayUs);
            $delayUs = min(2000000, (int)($delayUs * 1.5));
        }
    }
    return null;
};

// Lifecycle is deliberately rebuilt once after all parser writes. Doing this
// per message made the targeted reparse several times slower and caused
// unnecessary SQLite write-lock churn.
fwrite(STDOUT, "\nLifecycle opnieuw opbouwen...\n");
$lifecycleResult = $withBusyRetry(
    static fn() => $lifecycle->rebuild(),
    'Lifecycle'
);

// Seed newly created review rows once at the end.
(new ParserReviewRepository($pdo, new ParserKnowledgeRepository($pdo)))->seedPending();

// Final quality pass only for markets touched by the targeted reparse.
// This catches cross-batch market baselines after all lifecycle states are final.
$qualityResult = $affectedKeys !== []
    ? $withBusyRetry(
        static fn() => $quality->rebuildForItemKeys(array_keys($affectedKeys)),
        'Market quality'
      )
    : ['trusted'=>0,'uncertain'=>0,'outlier'=>0,'unpriced'=>0,'groups'=>0];

$duration = microtime(true) - $started;

fwrite(STDOUT, "\nKlaar.\n");
fwrite(STDOUT, "Gerichte berichten: {$processed}\n");
fwrite(STDOUT, "Structured offers:  {$created}\n");
fwrite(STDOUT, "Accepted:           {$accepted}\n");
fwrite(STDOUT, "Review:             {$review}\n");
fwrite(STDOUT, "Rejected:           {$rejected}\n");
fwrite(STDOUT, "Market keys geraakt: ".count($affectedKeys)."\n");
fwrite(STDOUT, 'Lifecycle: ' . json_encode($lifecycleResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDOUT, 'Market quality: ' . json_encode($qualityResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDOUT, sprintf("Duur: %.1f sec\n", $duration));
