<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require dirname(__DIR__) . '/app/V2/Database.php';
require dirname(__DIR__) . '/app/V2/Intelligence/CurrencyFormatter.php';
require dirname(__DIR__) . '/app/V2/Alerts/LiveFeedService.php';

use LittyWatch\V2\Alerts\LiveFeedService;
use LittyWatch\V2\Database;
use LittyWatch\V2\Intelligence\CurrencyFormatter;

try {
    $root = dirname(__DIR__);
    $pdo = Database::connect($root);
    $rows = (new LiveFeedService($pdo))->latest(min(300, max(1, (int)($_GET['limit'] ?? 100))));
    $money = new CurrencyFormatter($root);

    ob_start();
    foreach ($rows as $row) {
        $type = strtolower((string)$row['trade_type']);
        $deal = (string)$row['deal_label'];
        $dealClass = in_array($deal, ['Zeer goedkoop', 'Zeer sterke WTB', 'Onder markt', 'Boven markt'], true)
            ? 'good'
            : (in_array($deal, ['Duur', 'Lage WTB'], true) ? 'bad' : 'neutral');
        ?>
<a class="row" href="/v2-market.php?key=<?= rawurlencode((string)$row['market_key']) ?>">
<div><span class="badge <?= $type === 'buy' ? 'buy' : 'sell' ?>"><?= strtoupper(htmlspecialchars($type, ENT_QUOTES)) ?></span></div>
<div class="item"><strong><?= htmlspecialchars((string)$row['item'], ENT_QUOTES) ?></strong><small><?= htmlspecialchars((string)$row['raw_segment'], ENT_QUOTES) ?></small></div>
<div><strong><?= htmlspecialchars($money->ecto($row['unit_price_ecto'] !== null ? (float)$row['unit_price_ecto'] : null), ENT_QUOTES) ?></strong><div class="muted"><?= htmlspecialchars($money->armbrace($row['unit_price_ecto'] !== null ? (float)$row['unit_price_ecto'] : null), ENT_QUOTES) ?></div></div>
<div class="<?= $dealClass ?>"><?= htmlspecialchars($deal, ENT_QUOTES) ?><?= $row['difference_percent'] !== null ? ' (' . htmlspecialchars((string)$row['difference_percent'], ENT_QUOTES) . '%)' : '' ?></div>
<div><?= htmlspecialchars((string)$row['player'], ENT_QUOTES) ?></div>
<div class="muted"><?= htmlspecialchars((string)$row['posted_at'], ENT_QUOTES) ?></div>
</a>
        <?php
    }
    $html = ob_get_clean();

    echo json_encode(['ok' => true, 'count' => count($rows), 'html' => $html], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
