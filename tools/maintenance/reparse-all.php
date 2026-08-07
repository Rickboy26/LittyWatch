<?php
declare(strict_types=1);

/**
 * Phase 3F CLI full market reparse.
 *
 * Run from the project root:
 *   php tools/maintenance/reparse-all.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Dit onderhoudsscript mag alleen via CLI worden uitgevoerd.\n");
    exit(1);
}

@set_time_limit(0);
@ini_set('max_execution_time', '0');

$root = dirname(__DIR__, 2);
require $root . '/bootstrap.php';

use LittyWatch\Market\OfferLifecycleService;
use LittyWatch\Market\StructuredOfferWriter;
use LittyWatch\Market\VariantNormalizer;
use LittyWatch\Market\MarketQualityService;
use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$pdo = db();
installSchema();

$lifecycle = new OfferLifecycleService($pdo);
clearstatcache(true);
$parser = new ParserEngine(new Catalog($root . '/app/Data', $pdo));
$writer = new StructuredOfferWriter($pdo, $parser, new VariantNormalizer(), null);

$timestampRows = $pdo->query("SELECT id, posted_at, collected_at FROM messages")->fetchAll();
$timestampRepair = $pdo->prepare("UPDATE messages SET posted_at=? WHERE id=?");
$timestampsRepaired = 0;
foreach ($timestampRows as $timestampRow) {
    $postedAt = (string)$timestampRow['posted_at'];
    $replacement = null;
    if (preg_match('/^([0-9]{12,})$/', $postedAt, $match)) {
        $unix = (float)$match[1];
        while ($unix > 20000000000) $unix /= 1000;
        if ($unix > 946684800 && $unix < 4102444800) $replacement = date(DATE_ATOM, (int)$unix);
    } elseif (preg_match('/^(\d{4,})-/', $postedAt, $match)) {
        $year = (int)$match[1];
        if ($year < 2000 || $year > ((int)date('Y') + 2)) {
            $fallback = (string)($timestampRow['collected_at'] ?? '');
            $fallbackTs = strtotime($fallback);
            $replacement = ($fallbackTs !== false && $fallbackTs > 946684800 && $fallbackTs < 4102444800)
                ? date(DATE_ATOM, $fallbackTs)
                : date(DATE_ATOM);
        }
    }
    if ($replacement !== null && $replacement !== $postedAt) {
        $timestampRepair->execute([$replacement, (int)$timestampRow['id']]);
        $timestampsRepaired += $timestampRepair->rowCount();
    }
}

$total = (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
$legacyCreated = 0;
$structuredCreated = 0;
$processed = 0;
$lastId = 0;
$batchSize = 250;

fwrite(STDOUT, "LittyWatch Phase 3L.10 volledige reparse gestart ({$total} berichten).\n");

while (true) {
    $stmt = $pdo->prepare('SELECT id, message FROM messages WHERE id > :last_id ORDER BY id LIMIT :limit');
    $stmt->bindValue(':last_id', $lastId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if ($rows === []) break;

    foreach ($rows as $row) {
        $messageId = (int)$row['id'];
        $message = (string)$row['message'];
        $legacyCreated += saveOffers($messageId, $message);
        $structuredCreated += $writer->parseMessage($messageId, $message, true);
        $processed++;
        $lastId = $messageId;
    }

    $pct = $total > 0 ? round(($processed / $total) * 100, 1) : 100.0;
    fwrite(STDOUT, sprintf("%d/%d berichten (%.1f%%)\n", $processed, $total, $pct));
}

$lifecycleResult = $lifecycle->rebuild();
$marketQualityResult = (new MarketQualityService($pdo))->rebuildAll();
(new LittyWatch\Repositories\ParserReviewRepository($pdo, new LittyWatch\Repositories\ParserKnowledgeRepository($pdo)))->seedPending();

fwrite(STDOUT, "\nKlaar.\n");
fwrite(STDOUT, "Berichten: {$processed}\n");
fwrite(STDOUT, "Legacy offers: {$legacyCreated}\n");
fwrite(STDOUT, "Structured offers: {$structuredCreated}\n");
fwrite(STDOUT, "Timestamps gerepareerd: {$timestampsRepaired}\n");
fwrite(STDOUT, 'Lifecycle: ' . json_encode($lifecycleResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDOUT, 'Market quality: ' . json_encode($marketQualityResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
