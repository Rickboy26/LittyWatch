<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Encyclopedia/ItemEncyclopediaService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Encyclopedia\ItemEncyclopediaService;

$pdo = Database::connect(__DIR__);
$service = new ItemEncyclopediaService($pdo, __DIR__);
$query = trim((string)($_GET['q'] ?? ''));
$items = $service->items($query, 500);
$summary = $service->summary();

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch V2.7 Item Encyclopedia</title>
<style>
:root{color-scheme:dark;--bg:#080b10;--panel:#121824;--line:#293548;--text:#eef2f8;--muted:#9eabba;--gold:#d9b870}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 16% 0,#1c2738 0,#080b10 44%);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.wrap{max-width:1500px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;gap:20px}.eyebrow{font-size:11px;color:var(--gold);font-weight:900;letter-spacing:.16em}h1{margin:4px 0}.muted{color:var(--muted)}nav a{color:var(--muted);text-decoration:none;margin-left:14px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:11px;margin:20px 0}.card,.panel{background:rgba(18,24,36,.95);border:1px solid var(--line);border-radius:15px}.card{padding:15px}.card small{color:var(--muted)}.card strong{display:block;font-size:23px;margin-top:5px}.search{display:flex;gap:9px;padding:14px;margin-bottom:16px}.search input{flex:1;border:1px solid var(--line);border-radius:10px;background:#0e141d;color:var(--text);padding:11px}.search button{border:0;border-radius:10px;background:linear-gradient(135deg,#735a2b,#b68d46);color:white;font-weight:850;padding:0 20px}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(275px,1fr));gap:13px}.item{display:flex;gap:13px;padding:14px;color:inherit;text-decoration:none}.icon{width:58px;height:58px;border-radius:11px;border:1px solid var(--line);background:#0b1119;display:grid;place-items:center;overflow:hidden;flex:0 0 auto}.icon img{width:100%;height:100%;object-fit:contain}.placeholder{font-size:20px;color:var(--gold);font-weight:900}.item strong{display:block;font-size:15px}.meta{margin-top:6px;color:var(--muted);font-size:12px}@media(max-width:760px){.wrap{padding:17px}.top{flex-direction:column}.stats{grid-template-columns:1fr 1fr}}
</style>
</head>
<body><main class="wrap">
<header class="top"><div><div class="eyebrow">LITTYWATCH V2.7</div><h1>Item Encyclopedia</h1><div class="muted">Marktdata gecombineerd met lokale Wiki-metadata en afbeeldingen.</div></div><nav><a href="/v2-hub.php">Command Center</a><a href="/v2-markets.php">Markten</a><a href="/v2.php">Dashboard</a></nav></header>
<section class="stats">
<article class="card"><small>Catalogusitems</small><strong><?= (int)$summary['catalog_items'] ?></strong></article>
<article class="card"><small>Wiki-metadata</small><strong><?= (int)$summary['metadata_items'] ?></strong></article>
<article class="card"><small>Lokale afbeeldingen</small><strong><?= (int)$summary['cached_images'] ?></strong></article>
<article class="card"><small>Mislukte syncs</small><strong><?= (int)$summary['failed_syncs'] ?></strong></article>
</section>
<form class="panel search" method="get"><input type="search" name="q" value="<?= h($query) ?>" placeholder="Zoek item, categorie of beschrijving"><button>Zoeken</button></form>
<section class="grid">
<?php foreach ($items as $row): ?>
<a class="card item" href="/v2-item.php?key=<?= rawurlencode((string)$row['item_key']) ?>">
<div class="icon"><?php if (!empty($row['local_image'])): ?><img src="<?= h($row['local_image']) ?>" alt=""><?php else: ?><span class="placeholder"><?= h(mb_substr((string)$row['item'], 0, 1)) ?></span><?php endif; ?></div>
<div><strong><?= h($row['item']) ?></strong><div class="meta"><?= (int)$row['market_count'] ?> markten · <?= (int)$row['offers_count'] ?> offers</div><div class="meta"><?= h($row['wiki_title'] ?: 'Nog niet gekoppeld aan Wiki') ?></div></div>
</a>
<?php endforeach; ?>
</section>
</main></body></html>
