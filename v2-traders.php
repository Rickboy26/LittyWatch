<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Trader/TraderIntelligenceService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Trader\TraderIntelligenceService;

$pdo = Database::connect(__DIR__);
$service = new TraderIntelligenceService($pdo);

$query = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'activity');
$rows = $service->search($query, $sort, 250);
$counts = $service->counts();

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function selected(string $current, string $value): string { return $current === $value ? ' selected' : ''; }
function scoreClass(int $score): string { return $score >= 75 ? 'good' : ($score >= 45 ? 'mid' : 'low'); }
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch V2.4 Traders</title>
<style>
:root{color-scheme:dark;--bg:#080b10;--panel:#121824;--line:#293548;--text:#eef2f8;--muted:#9eabba;--gold:#d9b870;--green:#6bdba6;--red:#ef9191;--orange:#e7b56d}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 15% 0,#1a2435 0,#080b10 42%);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.wrap{max-width:1450px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.eyebrow{font-size:11px;color:var(--gold);font-weight:900;letter-spacing:.16em}.top h1{margin:4px 0 5px;font-size:32px}.muted{color:var(--muted)}nav a{color:var(--muted);text-decoration:none;margin-left:14px}nav a:hover{color:var(--gold)}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:22px 0}.card,.panel{background:rgba(18,24,36,.95);border:1px solid var(--line);border-radius:15px}.card{padding:16px}.card small{color:var(--muted)}.card strong{display:block;font-size:25px;margin-top:5px}.filters{display:grid;grid-template-columns:2fr 1fr auto;gap:10px;padding:15px;margin-bottom:16px}.filters input,.filters select,.filters button{width:100%;border:1px solid var(--line);border-radius:9px;background:#0e141d;color:var(--text);padding:10px 11px}.filters button{background:linear-gradient(135deg,#735a2b,#b68d46);font-weight:850;cursor:pointer}.panel{overflow:hidden}.table{overflow:auto}.row{min-width:1050px;display:grid;grid-template-columns:minmax(230px,2fr) 90px 90px 100px 100px 115px 105px 110px;gap:12px;align-items:center;padding:12px 16px;border-bottom:1px solid rgba(41,53,72,.72)}.row.header{font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:900}.row:last-child{border-bottom:0}.name strong{display:block;font-size:15px}.name small{color:var(--muted)}.badge{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:11px;font-weight:850;background:#263244}.good{color:var(--green);background:rgba(107,219,166,.12)}.mid{color:var(--orange);background:rgba(231,181,109,.12)}.low{color:var(--red);background:rgba(239,145,145,.12)}a.row{color:inherit;text-decoration:none}.empty{padding:28px;color:var(--muted)}@media(max-width:850px){.stats{grid-template-columns:1fr 1fr}.top{flex-direction:column}.filters{grid-template-columns:1fr}}@media(max-width:560px){.wrap{padding:17px}}
</style>
</head>
<body>
<main class="wrap">
<header class="top">
<div><div class="eyebrow">LITTYWATCH V2.4</div><h1>Trader Intelligence</h1><div class="muted">Activiteit en handelsgedrag op basis van publieke Kamadan-advertenties.</div></div>
<nav><a href="/v2.php">Dashboard</a><a href="/v2-markets.php">Markten</a><a href="/v2-intelligence.php">Intelligence</a><a href="/v2-watchlist.php">Watchlist</a></nav>
</header>

<section class="stats">
<article class="card"><small>Bekende traders</small><strong><?= (int)$counts['traders'] ?></strong></article>
<article class="card"><small>Actief laatste 24 uur</small><strong><?= (int)$counts['active_24h'] ?></strong></article>
<article class="card"><small>Structured offers</small><strong><?= (int)$counts['offers'] ?></strong></article>
<article class="card"><small>Offers met prijs</small><strong><?= (int)$counts['priced'] ?></strong></article>
</section>

<form class="panel filters" method="get">
<input type="search" name="q" value="<?= h($query) ?>" placeholder="Zoek spelersnaam">
<select name="sort">
<option value="activity"<?= selected($sort, 'activity') ?>>Laatst gezien</option>
<option value="offers"<?= selected($sort, 'offers') ?>>Meeste offers</option>
<option value="markets"<?= selected($sort, 'markets') ?>>Meeste markten</option>
<option value="buy"<?= selected($sort, 'buy') ?>>Meeste WTB</option>
<option value="sell"<?= selected($sort, 'sell') ?>>Meeste WTS</option>
<option value="confidence"<?= selected($sort, 'confidence') ?>>Hoogste betrouwbaarheid</option>
<option value="name"<?= selected($sort, 'name') ?>>Naam</option>
</select>
<button>Filteren</button>
</form>

<section class="panel">
<div class="table">
<div class="row header"><span>Trader</span><span>Offers</span><span>WTB</span><span>WTS</span><span>Markten</span><span>Handelsrol</span><span>Activiteit</span><span>Datakwaliteit</span></div>
<?php if ($rows === []): ?><div class="empty">Geen traders gevonden.</div><?php endif; ?>
<?php foreach ($rows as $row): ?>
<a class="row" href="/v2-trader.php?player=<?= rawurlencode((string)$row['player']) ?>">
<div class="name"><strong><?= h($row['player']) ?></strong><small>Laatst gezien: <?= h($row['last_seen'] ?? '—') ?></small></div>
<div><?= (int)$row['offers_count'] ?></div>
<div><?= (int)$row['buy_count'] ?></div>
<div><?= (int)$row['sell_count'] ?></div>
<div><?= (int)$row['market_count'] ?></div>
<div><span class="badge mid"><?= h($row['side_label']) ?></span></div>
<div><span class="badge <?= scoreClass((int)$row['activity_score']) ?>"><?= (int)$row['activity_score'] ?>/100</span></div>
<div><span class="badge <?= scoreClass((int)$row['reliability_score']) ?>"><?= (int)$row['reliability_score'] ?>/100</span></div>
</a>
<?php endforeach; ?>
</div>
</section>
</main>
</body>
</html>
