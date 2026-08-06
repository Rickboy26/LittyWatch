<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\Knowledge\Seeder;
use LittyWatch\Market\OfferLifecycleService;
use LittyWatch\Market\StructuredOfferWriter;
use LittyWatch\Market\VariantNormalizer;
use LittyWatch\V2\Alerts\LiveFeedService;
use LittyWatch\V2\Assets\AssetCatalogService;
use LittyWatch\V2\Intelligence\CurrencyFormatter;
use LittyWatch\V2\Intelligence\MarketIntelligenceService;
use LittyWatch\V2\SnapshotService;
use LittyWatch\V2\MarketStats;
use PDO;

final class MaintenanceController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly View $view,
        private readonly string $root,
    ) {}

    public function collect(Request $request): Response
    {
        return $this->resultPage('Kamadan ophalen', collectMessages(), '/admin');
    }

    public function reparse(Request $request): Response
    {
        $lifecycle = new OfferLifecycleService($this->pdo);
        $writer = new StructuredOfferWriter($this->pdo, parserV2(), new VariantNormalizer(), null);
        $rows = $this->pdo->query('SELECT id, message FROM messages ORDER BY id')->fetchAll();
        $created = 0;
        foreach ($rows as $row) {
            $created += $writer->parseMessage((int)$row['id'], (string)$row['message'], true);
        }
        return $this->resultPage('Parser v2 opnieuw uitgevoerd', [
            'messages_reparsed' => count($rows),
            'structured_offers_created' => $created,
            'lifecycle' => $lifecycle->rebuild(),
        ], '/structured-offers');
    }

    public function marketMaintenance(Request $request): Response
    {
        $result = (new OfferLifecycleService($this->pdo))->rebuild();
        return $this->resultPage('Market maintenance voltooid', $result, '/markets');
    }

    public function seedKnowledge(Request $request): Response
    {
        $stats = (new Seeder(
            $this->pdo,
            $this->root . '/app/Data/items.json',
            $this->root . '/app/Data/attributes.json',
            $this->root . '/app/Data/item-profiles.json'
        ))->run();
        return $this->resultPage('Knowledge Base bijgewerkt', $stats, '/knowledge');
    }

    public function intelligence(Request $request): Response
    {
        $result = (new MarketIntelligenceService($this->pdo))->rebuild();
        return $this->resultPage('Market Intelligence herberekend', $result, '/intelligence');
    }

    public function snapshot(Request $request): Response
    {
        $inserted = (new SnapshotService($this->pdo, new MarketStats($this->pdo)))->captureAll();
        $result = ['snapshots_created' => $inserted];
        return $this->resultPage('Marktsnapshot gemaakt', $result, '/trends');
    }

    public function parserLab(Request $request): Response
    {
        $examples = [
            'WTS BDS q9 FC 35a|q11 Inspa 12a|',
            'WTS Eternal Shields: Q9 comm 70e, Q9 motivation 40e, Q10 tact 65e',
            'WTS ObsiEdge / EternalBlade / VoltaicSpear (all unidentified) in the package 22a',
            'WTS q9 15^50 OS shadow bow 35e | obsi shards 2:1e || WTB Ektos! 7=100k (5x) zkeys! 1.3e/ea',
        ];
        $input = trim((string)($request->post['message'] ?? '')) ?: $examples[0];
        $offers = array_map(static fn($offer) => $offer->toArray(), parserV2()->parse($input));
        return Response::html($this->view->render('admin/parser-lab', [
            'title' => 'Parser Lab · LittyWatch',
            'input' => $input,
            'examples' => $examples,
            'offers' => $offers,
        ]));
    }

    public function liveApi(Request $request): Response
    {
        $rows = (new LiveFeedService($this->pdo))->latest(min(300, max(1, $request->int('limit', 100))));
        $money = new CurrencyFormatter($this->root);
        ob_start();
        foreach ($rows as $row) {
            $type = strtolower((string)$row['trade_type']);
            $deal = (string)$row['deal_label'];
            $dealClass = in_array($deal, ['Zeer goedkoop','Zeer sterke WTB','Onder markt','Boven markt'], true) ? 'good' : (in_array($deal, ['Duur','Lage WTB'], true) ? 'bad' : 'neutral');
            ?>
<a class="row" href="/market?key=<?= rawurlencode((string)$row['market_key']) ?>">
<div><span class="badge <?= $type === 'buy' ? 'buy' : 'sell' ?>"><?= h(strtoupper($type)) ?></span></div>
<div class="item"><strong><?= h($row['item']) ?></strong><small><?= h($row['raw_segment']) ?></small></div>
<div><strong><?= h($money->ecto($row['unit_price_ecto'] !== null ? (float)$row['unit_price_ecto'] : null)) ?></strong><div class="muted"><?= h($money->armbrace($row['unit_price_ecto'] !== null ? (float)$row['unit_price_ecto'] : null)) ?></div></div>
<div class="<?= h($dealClass) ?>"><?= h($deal) ?><?= $row['difference_percent'] !== null ? ' (' . h($row['difference_percent']) . '%)' : '' ?></div>
<div><?= h($row['player']) ?></div><div class="muted"><?= h($row['posted_at']) ?></div>
</a><?php
        }
        return Response::json(['ok'=>true,'count'=>count($rows),'html'=>(string)ob_get_clean()]);
    }

    private function resultPage(string $heading, array $result, string $back): Response
    {
        return Response::html($this->view->render('admin/result', [
            'title' => $heading . ' · LittyWatch',
            'heading' => $heading,
            'result' => $result,
            'back' => $back,
        ]));
    }
}
