<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/app/V2/Database.php';
require $root . '/app/V2/Intelligence/CurrencyFormatter.php';
require $root . '/app/V2/Alerts/LiveFeedService.php';

use LittyWatch\V2\Alerts\LiveFeedService;
use LittyWatch\V2\Database;
use LittyWatch\V2\Intelligence\CurrencyFormatter;

$pdo = Database::connect($root);
$service = new LiveFeedService($pdo);
$money = new CurrencyFormatter($root);
$rows = $service->latest(120);

function dealClass(string $label): string {
    return match ($label) {
        'Zeer goedkoop', 'Zeer sterke WTB', 'Onder markt', 'Boven markt' => 'good',
        'Duur', 'Lage WTB' => 'bad',
        default => 'neutral',
    };
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch Live Feed</title>
<style>
:root{color-scheme:dark;--bg:#080b10;--panel:#121824;--line:#293548;--text:#eef2f8;--muted:#9eabba;--gold:#d9b870;--green:#6bdba6;--red:#ef9191;--orange:#e7b56d}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 15% 0,#1a2435 0,#080b10 42%);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.wrap{max-width:1450px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;gap:20px}.eyebrow{font-size:11px;color:var(--gold);font-weight:900;letter-spacing:.16em}h1{margin:4px 0}.muted{color:var(--muted)}nav a{color:var(--muted);text-decoration:none;margin-left:14px}.toolbar{display:flex;gap:9px;margin:20px 0}.toolbar button{border:1px solid var(--line);background:#111824;color:var(--text);border-radius:9px;padding:9px 12px;cursor:pointer}.panel{background:rgba(18,24,36,.95);border:1px solid var(--line);border-radius:15px;overflow:hidden}.feed{overflow:auto}.row{min-width:980px;display:grid;grid-template-columns:70px minmax(230px,1.8fr) 120px 140px 130px 180px;gap:12px;align-items:center;padding:12px 16px;border-bottom:1px solid rgba(41,53,72,.72)}.row:last-child{border-bottom:0}.badge{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:11px;font-weight:850}.buy{color:var(--green);background:rgba(107,219,166,.12)}.sell{color:var(--red);background:rgba(239,145,145,.12)}.good{color:var(--green)}.bad{color:var(--red)}.neutral{color:var(--orange)}.item strong{display:block}.item small{color:var(--muted)}a{color:inherit;text-decoration:none}@media(max-width:750px){.wrap{padding:17px}.top{flex-direction:column}}
</style>
<link rel="stylesheet" href="/assets/v2/platform.css?v=310">
</head>
<body><main class="wrap">
<header class="top"><div><div class="eyebrow">LITTYWATCH</div><h1>Live Market Feed</h1><div class="muted">De pagina haalt iedere 20 seconden de nieuwste opgeslagen offers op.</div></div><nav><a href="/alerts">Alerts</a><a href="/markets">Markten</a><a href="/">Dashboard</a></nav></header>
<div class="toolbar"><button id="refresh">Nu vernieuwen</button><button id="pause">Pauzeren</button><span class="muted" id="status">Laatste update: <?= date('H:i:s') ?></span></div>
<section class="panel"><div class="feed" id="feed">
<?php foreach ($rows as $row): ?>
<a class="row" href="/market?key=<?= rawurlencode((string)$row['market_key']) ?>">
<div><span class="badge <?= strtolower((string)$row['trade_type']) === 'buy' ? 'buy' : 'sell' ?>"><?= strtoupper(h($row['trade_type'])) ?></span></div>
<div class="item"><strong><?= h($row['item']) ?></strong><small><?= h($row['raw_segment']) ?></small></div>
<div><strong><?= h($money->ecto($row['unit_price_ecto'] !== null ? (float)$row['unit_price_ecto'] : null)) ?></strong><div class="muted"><?= h($money->armbrace($row['unit_price_ecto'] !== null ? (float)$row['unit_price_ecto'] : null)) ?></div></div>
<div class="<?= dealClass((string)$row['deal_label']) ?>"><?= h($row['deal_label']) ?><?= $row['difference_percent'] !== null ? ' (' . h($row['difference_percent']) . '%)' : '' ?></div>
<div><?= h($row['player']) ?></div>
<div class="muted"><?= h($row['posted_at']) ?></div>
</a>
<?php endforeach; ?>
</div></section>
</main>
<script>
let paused=false;
const status=document.getElementById('status');
async function reloadFeed(){
 if(paused)return;
 try{
   const response=await fetch('/api/live?limit=120',{cache:'no-store'});
   const data=await response.json();
   if(!data.ok)throw new Error(data.error||'API-fout');
   const feed=document.getElementById('feed');
   feed.innerHTML=data.html;
   status.textContent='Laatste update: '+new Date().toLocaleTimeString('nl-NL');
 }catch(error){status.textContent='Updatefout: '+error.message;}
}
document.getElementById('refresh').onclick=reloadFeed;
document.getElementById('pause').onclick=function(){paused=!paused;this.textContent=paused?'Hervatten':'Pauzeren';if(!paused)reloadFeed();};
setInterval(reloadFeed,20000);
</script>
<script src="/assets/v2/platform.js?v=310"></script>
</body></html>
