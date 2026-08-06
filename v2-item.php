<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Intelligence/CurrencyFormatter.php';
require __DIR__ . '/app/V2/Encyclopedia/ItemEncyclopediaService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Encyclopedia\ItemEncyclopediaService;
use LittyWatch\V2\Intelligence\CurrencyFormatter;

$key = trim((string)($_GET['key'] ?? ''));
$pdo = Database::connect(__DIR__);
$service = new ItemEncyclopediaService($pdo, __DIR__);
$money = new CurrencyFormatter(__DIR__);
$item = $service->item($key);

if ($item === null) {
    http_response_code(404);
    echo 'Item niet gevonden.';
    exit;
}

$error = null;
$syncResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $syncResult = $service->sync($key, (string)($_POST['wiki_title'] ?? ''));
        $item = $service->item($key) ?? $item;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$markets = $service->markets($key, 100);

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($item['item']) ?> — LittyWatch</title>
<style>
:root{color-scheme:dark;--bg:#080b10;--panel:#121824;--line:#293548;--text:#eef2f8;--muted:#9eabba;--gold:#d9b870;--green:#6bdba6;--red:#ef9191}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 16% 0,#1c2738 0,#080b10 44%);color:var(--text);font:14px/1.5 Inter,system-ui,sans-serif}.wrap{max-width:1400px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;gap:20px}.eyebrow{font-size:11px;color:var(--gold);font-weight:900;letter-spacing:.16em}h1{margin:4px 0}.muted{color:var(--muted)}nav a{color:var(--muted);text-decoration:none;margin-left:14px}.hero{display:grid;grid-template-columns:150px 1fr;gap:22px;margin:22px 0}.image,.panel,.card{background:rgba(18,24,36,.95);border:1px solid var(--line);border-radius:15px}.image{width:150px;height:150px;display:grid;place-items:center;overflow:hidden}.image img{width:100%;height:100%;object-fit:contain}.placeholder{font-size:48px;color:var(--gold);font-weight:900}.details{padding:18px}.details p{max-width:900px}.chips{display:flex;gap:7px;flex-wrap:wrap;margin-top:12px}.chip{padding:5px 8px;border-radius:999px;background:#263244;color:var(--muted);font-size:11px}.sync{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:15px}.sync input,.sync button{border:1px solid var(--line);border-radius:9px;background:#0e141d;color:var(--text);padding:10px}.sync button{background:linear-gradient(135deg,#735a2b,#b68d46);font-weight:850}.message{padding:10px;border-radius:9px;margin-top:10px}.ok{color:var(--green);background:rgba(107,219,166,.12)}.error{color:var(--red);background:rgba(239,145,145,.12)}.panel{overflow:hidden}.panel h2{margin:0;padding:15px 17px;border-bottom:1px solid var(--line)}.row{min-width:900px;display:grid;grid-template-columns:minmax(260px,1.6fr) 110px 110px 90px 100px;gap:12px;padding:11px 16px;border-bottom:1px solid rgba(41,53,72,.7);align-items:center}.row:last-child{border-bottom:0}.table{overflow:auto}a.row{color:inherit;text-decoration:none}.row code{display:block;color:var(--muted);font-size:11px}@media(max-width:700px){.wrap{padding:17px}.top{flex-direction:column}.hero{grid-template-columns:1fr}.image{width:110px;height:110px}.sync{grid-template-columns:1fr}}
</style>
</head>
<body><main class="wrap">
<header class="top"><div><div class="eyebrow">ITEM ENCYCLOPEDIA</div><h1><?= h($item['item']) ?></h1><div class="muted"><?= h($item['item_key']) ?></div></div><nav><a href="/v2-items.php">Alle items</a><a href="/v2-markets.php">Markten</a><a href="/v2-hub.php">Command Center</a></nav></header>
<section class="hero">
<div class="image"><?php if (!empty($item['local_image'])): ?><img src="<?= h($item['local_image']) ?>" alt=""><?php else: ?><span class="placeholder"><?= h(mb_substr((string)$item['item'], 0, 1)) ?></span><?php endif; ?></div>
<div class="card details">
<p><?= h($item['description'] ?: 'Voor dit item is nog geen Wiki-beschrijving opgehaald.') ?></p>
<div class="chips"><span class="chip"><?= (int)$item['market_count'] ?> markten</span><span class="chip"><?= (int)$item['offers_count'] ?> offers</span><span class="chip"><?= (int)$item['trader_count'] ?> traders</span><?php if ($item['wiki_title']): ?><span class="chip"><?= h($item['wiki_title']) ?></span><?php endif; ?></div>
<form class="sync" method="post"><input name="wiki_title" value="<?= h($item['wiki_title'] ?: $item['item']) ?>" placeholder="Titel op Guild Wars Wiki"><button>Wiki synchroniseren</button></form>
<?php if ($syncResult): ?><div class="message ok">Wiki-metadata bijgewerkt.</div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= h($error) ?></div><?php endif; ?>
<?php if ($item['source_url']): ?><p class="muted">Bron: <a style="color:inherit" href="<?= h($item['source_url']) ?>" rel="noopener noreferrer">Guild Wars Wiki</a></p><?php endif; ?>
</div>
</section>
<section class="panel"><h2>Marktvarianten</h2><div class="table">
<?php foreach ($markets as $row): ?><a class="row" href="/v2-market.php?key=<?= rawurlencode((string)$row['market_key']) ?>"><div><strong><?= h($row['item']) ?></strong><code><?= h($row['market_key']) ?></code></div><div>WTB <?= h($money->ecto($row['best_wtb_ecto'] !== null ? (float)$row['best_wtb_ecto'] : null)) ?></div><div>WTS <?= h($money->ecto($row['best_wts_ecto'] !== null ? (float)$row['best_wts_ecto'] : null)) ?></div><div><?= (int)$row['unique_traders'] ?> traders</div><div><?= (int)$row['confidence_score'] ?>/100</div></a><?php endforeach; ?>
</div></section>
</main></body></html>
