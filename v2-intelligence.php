<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Intelligence/Schema.php';
require __DIR__ . '/app/V2/Intelligence/MarketIntelligenceService.php';
require __DIR__ . '/app/V2/Intelligence/CurrencyFormatter.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Intelligence\CurrencyFormatter;
use LittyWatch\V2\Intelligence\MarketIntelligenceService;

$pdo = Database::connect(__DIR__);
$service = new MarketIntelligenceService($pdo);
$deals = $service->topDeals(30);
$markets = $service->activeMarkets(100);
$money = new CurrencyFormatter(__DIR__);

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function scoreClass(int $score): string { return $score >= 75 ? 'good' : ($score >= 45 ? 'mid' : 'low'); }
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch V2.2 Market Intelligence</title>
<style>
:root{color-scheme:dark;--bg:#090c11;--panel:#121823;--panel2:#18202d;--line:#283345;--text:#edf1f7;--muted:#9da9bb;--gold:#d7b56d;--green:#65d69e;--red:#ed8b8b;--orange:#e6b36d}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 10% 0,#182030 0,#090c11 42%);color:var(--text);font:14px/1.5 Inter,system-ui,sans-serif}.wrap{max-width:1440px;margin:auto;padding:28px}.top{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:22px}.eyebrow{font-size:11px;letter-spacing:.15em;color:var(--gold);font-weight:800}.top h1{margin:3px 0 5px;font-size:32px}.muted{color:var(--muted)}nav a{color:var(--muted);text-decoration:none;margin-left:14px}nav a:hover{color:var(--gold)}.actions{display:flex;gap:10px;flex-wrap:wrap}.button{display:inline-flex;padding:10px 14px;border-radius:10px;background:var(--panel2);border:1px solid var(--line);color:var(--text);text-decoration:none}.button.primary{background:linear-gradient(135deg,#745c2c,#b58c45);border-color:#c59a50}.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:20px 0}.card,.panel{background:rgba(18,24,35,.94);border:1px solid var(--line);border-radius:15px}.card{padding:17px}.card small{display:block;color:var(--muted)}.card strong{display:block;font-size:25px;margin-top:5px}.panel{overflow:hidden;margin-top:18px}.panel-head{display:flex;justify-content:space-between;align-items:center;padding:17px 18px;border-bottom:1px solid var(--line)}.panel-head h2{margin:0;font-size:18px}.table{overflow:auto}.row{display:grid;grid-template-columns:minmax(220px,2fr) 90px 90px 105px 105px 90px 100px 100px;align-items:center;gap:12px;padding:12px 18px;border-bottom:1px solid rgba(40,51,69,.7)}.row.header{font-size:11px;text-transform:uppercase;color:var(--muted);font-weight:800}.row:last-child{border-bottom:0}.item a{color:var(--text);font-weight:750;text-decoration:none}.item code{display:block;color:var(--muted);font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.price strong{display:block}.price small{color:var(--muted)}.badge{display:inline-flex;justify-content:center;padding:5px 8px;border-radius:999px;font-size:11px;font-weight:800;background:#242e3c}.badge.good{color:var(--green);background:rgba(101,214,158,.12)}.badge.mid{color:var(--orange);background:rgba(230,179,109,.12)}.badge.low{color:var(--red);background:rgba(237,139,139,.12)}.score{font-size:18px;font-weight:900}.spread.positive{color:var(--green)}.spread.negative{color:var(--red)}.empty{padding:25px;color:var(--muted)}@media(max-width:1000px){.summary{grid-template-columns:repeat(2,1fr)}.row{min-width:1050px}}@media(max-width:620px){.wrap{padding:18px}.top{flex-direction:column}.summary{grid-template-columns:1fr 1fr}.card strong{font-size:20px}}
</style>
</head>
<body>
<main class="wrap">
<header class="top">
<div><div class="eyebrow">LITTYWATCH V2.2</div><h1>Market Intelligence</h1><div class="muted">Scores op basis van actieve, geaccepteerde structured offers.</div></div>
<div><nav><a href="/v2.php">Dashboard</a><a href="/v2-watchlist.php">Watchlist</a><a href="/v2-trends.php">Trends</a></nav><div class="actions"><a class="button" href="/v2-intelligence-refresh.php">Herberekenen</a></div></div>
</header>
<?php
$totalMarkets = count($markets);
$dealCount = count(array_filter($deals, static fn(array $d): bool => (int)$d['deal_score'] >= 60));
$highConfidence = count(array_filter($markets, static fn(array $d): bool => (int)$d['confidence_score'] >= 80));
$highLiquidity = count(array_filter($markets, static fn(array $d): bool => (int)$d['liquidity_score'] >= 75));
?>
<section class="summary"><article class="card"><small>Markten</small><strong><?= $totalMarkets ?></strong></article><article class="card"><small>Deal score ≥ 60</small><strong><?= $dealCount ?></strong></article><article class="card"><small>Hoge zekerheid</small><strong><?= $highConfidence ?></strong></article><article class="card"><small>Hoge liquiditeit</small><strong><?= $highLiquidity ?></strong></article></section>
<section class="panel"><div class="panel-head"><h2>Beste mogelijke spreads</h2><span class="muted">Alle prijzen per stuk</span></div><div class="table"><div class="row header"><span>Markt</span><span>WTB</span><span>WTS</span><span>Spread</span><span>Deal score</span><span>Zekerheid</span><span>Liquiditeit</span><span>Vraag</span></div><?php if($deals===[]): ?><div class="empty">Nog geen vergelijkbare WTB- en WTS-prijzen. Herbereken nadat meer data is verzameld.</div><?php endif; ?><?php foreach($deals as $row): $spread=(float)($row['spread_ecto'] ?? 0); ?><div class="row"><div class="item"><a href="/market?key=<?= rawurlencode((string)$row['market_key']) ?>"><?= h($row['item']) ?></a><code><?= h($row['market_key']) ?></code></div><div class="price"><strong><?= h($money->ecto($row['best_wtb_ecto'] !== null ? (float)$row['best_wtb_ecto'] : null)) ?></strong><small><?= h($money->armbrace($row['best_wtb_ecto'] !== null ? (float)$row['best_wtb_ecto'] : null)) ?></small></div><div class="price"><strong><?= h($money->ecto($row['best_wts_ecto'] !== null ? (float)$row['best_wts_ecto'] : null)) ?></strong><small><?= h($money->armbrace($row['best_wts_ecto'] !== null ? (float)$row['best_wts_ecto'] : null)) ?></small></div><div class="spread <?= $spread >= 0 ? 'positive':'negative' ?>"><?= h($money->ecto($spread)) ?></div><div class="score"><?= (int)$row['deal_score'] ?>/100</div><div><span class="badge <?= scoreClass((int)$row['confidence_score']) ?>"><?= (int)$row['confidence_score'] ?>%</span></div><div><span class="badge <?= scoreClass((int)$row['liquidity_score']) ?>"><?= h($row['liquidity_label']) ?></span></div><div><span class="badge mid"><?= h($row['demand_label']) ?></span></div></div><?php endforeach; ?></div></section>
<section class="panel"><div class="panel-head"><h2>Alle actieve markten</h2><span class="muted">Nieuwste activiteit eerst</span></div><div class="table"><div class="row header"><span>Markt</span><span>Mediaan WTB</span><span>Mediaan WTS</span><span>Offers</span><span>Traders</span><span>Zekerheid</span><span>Liquiditeit</span><span>Vraag</span></div><?php foreach($markets as $row): ?><div class="row"><div class="item"><a href="/market?key=<?= rawurlencode((string)$row['market_key']) ?>"><?= h($row['item']) ?></a><code><?= h($row['market_key']) ?></code></div><div class="price"><strong><?= h($money->ecto($row['median_wtb_ecto'] !== null ? (float)$row['median_wtb_ecto'] : null)) ?></strong><small><?= h($money->armbrace($row['median_wtb_ecto'] !== null ? (float)$row['median_wtb_ecto'] : null)) ?></small></div><div class="price"><strong><?= h($money->ecto($row['median_wts_ecto'] !== null ? (float)$row['median_wts_ecto'] : null)) ?></strong><small><?= h($money->armbrace($row['median_wts_ecto'] !== null ? (float)$row['median_wts_ecto'] : null)) ?></small></div><div><?= (int)$row['buy_offers'] + (int)$row['sell_offers'] ?></div><div><?= (int)$row['unique_traders'] ?></div><div><span class="badge <?= scoreClass((int)$row['confidence_score']) ?>"><?= (int)$row['confidence_score'] ?>%</span></div><div><span class="badge <?= scoreClass((int)$row['liquidity_score']) ?>"><?= h($row['liquidity_label']) ?></span></div><div><span class="badge mid"><?= h($row['demand_label']) ?></span></div></div><?php endforeach; ?></div></section>
</main>
</body>
</html>
