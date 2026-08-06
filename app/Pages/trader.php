<?php

declare(strict_types=1);

$root = dirname($root, 2);

require $root . '/app/V2/Database.php';
require $root . '/app/V2/Intelligence/CurrencyFormatter.php';
require $root . '/app/V2/Trader/TraderIntelligenceService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Intelligence\CurrencyFormatter;
use LittyWatch\V2\Trader\TraderIntelligenceService;

$player = trim((string)($_GET['player'] ?? ''));
$pdo = Database::connect($root);
$service = new TraderIntelligenceService($pdo);
$money = new CurrencyFormatter($root);
$profile = $service->profile($player);

if ($profile === null) {
    http_response_code(404);
    echo 'Trader niet gevonden.';
    exit;
}

$markets = $service->topMarkets($player, 25);
$offers = $service->recentOffers($player, 200);

function scoreClass(int $score): string { return $score >= 75 ? 'good' : ($score >= 45 ? 'mid' : 'low'); }
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($player) ?> — LittyWatch</title>
<style>
:root{color-scheme:dark;--bg:#080b10;--panel:#121824;--line:#293548;--text:#eef2f8;--muted:#9eabba;--gold:#d9b870;--green:#6bdba6;--red:#ef9191;--orange:#e7b56d}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 15% 0,#1a2435 0,#080b10 42%);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.wrap{max-width:1450px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.eyebrow{font-size:11px;color:var(--gold);font-weight:900;letter-spacing:.16em}.top h1{margin:4px 0 5px;font-size:32px}.muted{color:var(--muted)}a{color:inherit;text-decoration:none}nav a{color:var(--muted);margin-left:14px}nav a:hover{color:var(--gold)}.stats{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin:22px 0}.card,.panel{background:rgba(18,24,36,.95);border:1px solid var(--line);border-radius:15px}.card{padding:16px}.card small{color:var(--muted)}.card strong{display:block;font-size:23px;margin-top:5px}.grid{display:grid;grid-template-columns:1fr 1.45fr;gap:16px}.panel{overflow:hidden}.panel h2{font-size:18px;margin:0;padding:16px 18px;border-bottom:1px solid var(--line)}.market{display:grid;grid-template-columns:minmax(180px,1.5fr) 70px 70px 95px;gap:10px;padding:11px 16px;border-bottom:1px solid rgba(41,53,72,.7)}.market:last-child{border-bottom:0}.market code{display:block;color:var(--muted);font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.offers{overflow:auto;max-height:850px}.offer{min-width:760px;display:grid;grid-template-columns:70px minmax(200px,1.8fr) 110px 150px;gap:12px;padding:11px 16px;border-bottom:1px solid rgba(41,53,72,.7);align-items:center}.offer:last-child{border-bottom:0}.badge{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:11px;font-weight:850;background:#263244}.buy{color:var(--green);background:rgba(107,219,166,.12)}.sell{color:var(--red);background:rgba(239,145,145,.12)}.good{color:var(--green);background:rgba(107,219,166,.12)}.mid{color:var(--orange);background:rgba(231,181,109,.12)}.low{color:var(--red);background:rgba(239,145,145,.12)}.price strong{display:block}.price small{color:var(--muted)}@media(max-width:1050px){.stats{grid-template-columns:repeat(3,1fr)}.grid{grid-template-columns:1fr}}@media(max-width:620px){.wrap{padding:17px}.stats{grid-template-columns:1fr 1fr}.top{flex-direction:column}}
</style>
<link rel="stylesheet" href="/assets/v2/platform.css?v=310">
</head>
<body>
<main class="wrap">
<header class="top">
<div><div class="eyebrow">TRADERPROFIEL</div><h1><?= h($player) ?></h1><div class="muted">Publieke handelsactiviteit; dit is geen reputatie- of fraudebeoordeling.</div></div>
<nav><a href="/traders">Alle traders</a><a href="/markets">Markten</a><a href="/">Dashboard</a></nav>
</header>

<section class="stats">
<article class="card"><small>Offers</small><strong><?= (int)$profile['offers_count'] ?></strong></article>
<article class="card"><small>WTB</small><strong><?= (int)$profile['buy_count'] ?></strong></article>
<article class="card"><small>WTS</small><strong><?= (int)$profile['sell_count'] ?></strong></article>
<article class="card"><small>Markten</small><strong><?= (int)$profile['market_count'] ?></strong></article>
<article class="card"><small>Activiteit</small><strong><?= (int)$profile['activity_score'] ?>/100</strong></article>
<article class="card"><small>Datakwaliteit</small><strong><?= (int)$profile['reliability_score'] ?>/100</strong></article>
</section>

<div class="grid">
<section class="panel">
<h2>Meest gebruikte markten</h2>
<?php if ($markets === []): ?><div style="padding:18px" class="muted">Geen structured markets beschikbaar.</div><?php endif; ?>
<?php foreach ($markets as $row): ?>
<a class="market" href="/market?key=<?= rawurlencode((string)$row['market_key']) ?>">
<div><strong><?= h($row['item']) ?></strong><code><?= h($row['market_key']) ?></code></div>
<div><?= (int)$row['buy_count'] ?> WTB</div>
<div><?= (int)$row['sell_count'] ?> WTS</div>
<div class="price"><strong><?= h($money->ecto($row['average_price_ecto'] !== null ? (float)$row['average_price_ecto'] : null)) ?></strong><small>gemiddeld</small></div>
</a>
<?php endforeach; ?>
</section>

<section class="panel">
<h2>Recente advertenties</h2>
<div class="offers">
<?php foreach ($offers as $row): ?>
<a class="offer" href="/market?key=<?= rawurlencode((string)$row['market_key']) ?>">
<div><span class="badge <?= strtolower((string)$row['trade_type']) === 'buy' ? 'buy' : 'sell' ?>"><?= strtoupper(h($row['trade_type'])) ?></span></div>
<div><strong><?= h($row['item']) ?></strong><div class="muted"><?= h($row['raw_segment']) ?></div></div>
<div class="price"><strong><?= h($money->ecto($row['unit_price_ecto'] !== null ? (float)$row['unit_price_ecto'] : null)) ?></strong><small><?= h($money->armbrace($row['unit_price_ecto'] !== null ? (float)$row['unit_price_ecto'] : null)) ?></small></div>
<div class="muted"><?= h($row['posted_at'] ?? '—') ?></div>
</a>
<?php endforeach; ?>
</div>
</section>
</div>
</main>
<script src="/assets/v2/platform.js?v=310"></script>
</body>
</html>
