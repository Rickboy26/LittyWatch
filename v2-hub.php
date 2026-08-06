<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Intelligence/CurrencyFormatter.php';
require __DIR__ . '/app/V2/Search/GlobalSearchService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Intelligence\CurrencyFormatter;
use LittyWatch\V2\Search\GlobalSearchService;

$pdo = Database::connect(__DIR__);
$service = new GlobalSearchService($pdo);
$money = new CurrencyFormatter(__DIR__);

$query = trim((string)($_GET['q'] ?? ''));
$results = $service->search($query, 12);
$summary = $service->summary();
$deals = $service->hotDeals(8);
$watchlist = $service->watchlistMarkets(8);
$events = $service->recentAlertEvents(8);

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function scoreClass(int|float|null $score): string {
    $value = (float)($score ?? 0);
    return $value >= 75 ? 'good' : ($value >= 45 ? 'mid' : 'low');
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch V2.6 Command Center</title>
<style>
:root{color-scheme:dark;--bg:#080b10;--panel:#121824;--panel2:#0e141d;--line:#293548;--text:#eef2f8;--muted:#9eabba;--gold:#d9b870;--green:#6bdba6;--red:#ef9191;--orange:#e7b56d}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 16% 0,#1c2738 0,#080b10 44%);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.shell{display:grid;grid-template-columns:230px 1fr;min-height:100vh}.side{border-right:1px solid var(--line);background:rgba(10,14,21,.95);padding:24px 16px;position:sticky;top:0;height:100vh}.brand{padding:5px 10px 23px}.brand strong{display:block;color:var(--gold);font-size:19px}.brand small{color:var(--muted)}.nav{display:grid;gap:5px}.nav a{color:var(--muted);text-decoration:none;padding:10px 11px;border-radius:9px}.nav a:hover,.nav a.active{background:#172130;color:var(--text)}.main{padding:28px;max-width:1550px;width:100%;margin:auto}.eyebrow{font-size:11px;color:var(--gold);font-weight:900;letter-spacing:.16em}.hero h1{font-size:34px;margin:5px 0}.muted{color:var(--muted)}.search{display:flex;gap:8px;margin:20px 0}.search input{flex:1;border:1px solid var(--line);border-radius:12px;background:var(--panel2);color:var(--text);padding:13px 15px;font-size:15px}.search button{border:0;border-radius:12px;padding:0 22px;background:linear-gradient(135deg,#735a2b,#b68d46);color:white;font-weight:850;cursor:pointer}.stats{display:grid;grid-template-columns:repeat(6,1fr);gap:11px;margin-bottom:16px}.card,.panel{background:rgba(18,24,36,.95);border:1px solid var(--line);border-radius:15px}.card{padding:15px}.card small{color:var(--muted)}.card strong{display:block;font-size:22px;margin-top:5px}.grid{display:grid;grid-template-columns:1.2fr .8fr;gap:16px}.panel{overflow:hidden}.panel h2{font-size:17px;margin:0;padding:15px 17px;border-bottom:1px solid var(--line)}.entry{display:grid;grid-template-columns:minmax(190px,1.6fr) 110px 110px 95px;gap:10px;padding:11px 16px;border-bottom:1px solid rgba(41,53,72,.7);align-items:center}.entry:last-child{border-bottom:0}.entry strong{display:block}.entry small{color:var(--muted)}a.entry{color:inherit;text-decoration:none}.badge{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:11px;font-weight:850}.good{color:var(--green);background:rgba(107,219,166,.12)}.mid{color:var(--orange);background:rgba(231,181,109,.12)}.low{color:var(--red);background:rgba(239,145,145,.12)}.result-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}.result{padding:12px 15px;border-bottom:1px solid rgba(41,53,72,.7)}.result:last-child{border-bottom:0}.result a{color:inherit;text-decoration:none}.empty{padding:18px;color:var(--muted)}@media(max-width:1150px){.stats{grid-template-columns:repeat(3,1fr)}.grid,.result-grid{grid-template-columns:1fr}}@media(max-width:820px){.shell{display:block}.side{position:static;height:auto;border-right:0;border-bottom:1px solid var(--line)}.nav{grid-template-columns:repeat(3,1fr)}.main{padding:18px}}@media(max-width:560px){.stats{grid-template-columns:1fr 1fr}.nav{grid-template-columns:1fr 1fr}.entry{grid-template-columns:1fr}.search{flex-direction:column}.search button{padding:12px}}
</style>
</head>
<body>
<div class="shell">
<aside class="side">
<div class="brand"><strong>LittyWatch</strong><small>V2.6 Command Center</small></div>
<nav class="nav">
<a class="active" href="/v2-hub.php">Overzicht</a>
<a href="/v2-markets.php">Markten</a>
<a href="/v2-live.php">Live feed</a>
<a href="/v2-traders.php">Traders</a>
<a href="/v2-watchlist.php">Watchlist</a>
<a href="/v2-alerts.php">Alerts</a>
<a href="/v2-trends.php">Trends</a>
<a href="/v2-intelligence.php">Intelligence</a>
</nav>
</aside>

<main class="main">
<section class="hero">
<div class="eyebrow">LITTYWATCH V2.6</div>
<h1>Market Command Center</h1>
<div class="muted">Zoek tegelijk in markten, items, traders en recente aanbiedingen.</div>
</section>

<form class="search" method="get">
<input type="search" name="q" value="<?= h($query) ?>" placeholder="Bijvoorbeeld: BDS q9 insp, Eternal Blade, Slayer Litty">
<button>Zoeken</button>
</form>

<section class="stats">
<article class="card"><small>Markten</small><strong><?= (int)$summary['markets'] ?></strong></article>
<article class="card"><small>Structured offers</small><strong><?= (int)$summary['offers'] ?></strong></article>
<article class="card"><small>Traders</small><strong><?= (int)$summary['traders'] ?></strong></article>
<article class="card"><small>Actieve alerts</small><strong><?= (int)$summary['alerts'] ?></strong></article>
<article class="card"><small>Watchlist</small><strong><?= (int)$summary['watchlist'] ?></strong></article>
<article class="card"><small>Snapshots</small><strong><?= (int)$summary['snapshots'] ?></strong></article>
</section>

<?php if ($query !== ''): ?>
<section class="result-grid">
<div class="panel">
<h2>Markten</h2>
<?php if ($results['markets'] === []): ?><div class="empty">Geen markten gevonden.</div><?php endif; ?>
<?php foreach ($results['markets'] as $row): ?>
<div class="result"><a href="/v2-market.php?key=<?= rawurlencode((string)$row['market_key']) ?>"><strong><?= h($row['item']) ?></strong><div class="muted"><?= h($row['market_key']) ?></div></a></div>
<?php endforeach; ?>
</div>

<div class="panel">
<h2>Items</h2>
<?php if ($results['items'] === []): ?><div class="empty">Geen items gevonden.</div><?php endif; ?>
<?php foreach ($results['items'] as $row): ?>
<div class="result"><a href="/v2-markets.php?q=<?= rawurlencode((string)$row['item']) ?>"><strong><?= h($row['item']) ?></strong><div class="muted"><?= (int)$row['offers_count'] ?> offers · <?= (int)$row['market_count'] ?> markten</div></a></div>
<?php endforeach; ?>
</div>

<div class="panel">
<h2>Traders</h2>
<?php if ($results['traders'] === []): ?><div class="empty">Geen traders gevonden.</div><?php endif; ?>
<?php foreach ($results['traders'] as $row): ?>
<div class="result"><a href="/v2-trader.php?player=<?= rawurlencode((string)$row['player']) ?>"><strong><?= h($row['player']) ?></strong><div class="muted"><?= (int)$row['messages_count'] ?> berichten · laatst <?= h($row['last_seen']) ?></div></a></div>
<?php endforeach; ?>
</div>

<div class="panel">
<h2>Recente aanbiedingen</h2>
<?php if ($results['offers'] === []): ?><div class="empty">Geen aanbiedingen gevonden.</div><?php endif; ?>
<?php foreach ($results['offers'] as $row): ?>
<div class="result"><a href="/v2-market.php?key=<?= rawurlencode((string)$row['market_key']) ?>"><strong><?= strtoupper(h($row['trade_type'])) ?> · <?= h($row['item']) ?></strong><div class="muted"><?= h($row['player']) ?> · <?= h($money->ecto($row['unit_price_ecto'] !== null ? (float)$row['unit_price_ecto'] : null)) ?> · <?= h($row['raw_segment']) ?></div></a></div>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>

<div class="grid" style="margin-top:16px">
<section class="panel">
<h2>Beste actuele deals</h2>
<?php if ($deals === []): ?><div class="empty">Nog geen intelligence-data beschikbaar.</div><?php endif; ?>
<?php foreach ($deals as $row): ?>
<a class="entry" href="/v2-market.php?key=<?= rawurlencode((string)$row['market_key']) ?>">
<div><strong><?= h($row['item']) ?></strong><small><?= h($row['market_key']) ?></small></div>
<div>WTB <?= h($money->ecto((float)$row['best_wtb_ecto'])) ?></div>
<div>WTS <?= h($money->ecto((float)$row['best_wts_ecto'])) ?></div>
<div><span class="badge <?= scoreClass($row['deal_score']) ?>"><?= (int)$row['deal_score'] ?>/100</span></div>
</a>
<?php endforeach; ?>
</section>

<section class="panel">
<h2>Watchlist</h2>
<?php if ($watchlist === []): ?><div class="empty">Je watchlist is nog leeg.</div><?php endif; ?>
<?php foreach ($watchlist as $row): ?>
<a class="entry" style="grid-template-columns:minmax(180px,1.5fr) 100px 100px" href="/v2-market.php?key=<?= rawurlencode((string)$row['market_key']) ?>">
<div><strong><?= h($row['label'] !== '' ? $row['label'] : ($row['item'] ?? $row['market_key'])) ?></strong><small><?= h($row['market_key']) ?></small></div>
<div><?= h($money->ecto($row['best_wtb_ecto'] !== null ? (float)$row['best_wtb_ecto'] : null)) ?> WTB</div>
<div><?= h($money->ecto($row['best_wts_ecto'] !== null ? (float)$row['best_wts_ecto'] : null)) ?> WTS</div>
</a>
<?php endforeach; ?>
</section>
</div>

<section class="panel" style="margin-top:16px">
<h2>Recente alert-events</h2>
<?php if ($events === []): ?><div class="empty">Nog geen alerts getriggerd.</div><?php endif; ?>
<?php foreach ($events as $row): ?>
<div class="result"><strong><?= h($row['message']) ?></strong><div class="muted"><?= h($row['created_at']) ?> · <?= h($row['market_key']) ?></div></div>
<?php endforeach; ?>
</section>
</main>
</div>
</body>
</html>
