<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Schema.php';
require __DIR__ . '/app/V2/WatchlistService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Schema;
use LittyWatch\V2\WatchlistService;

$pdo = Database::connect(__DIR__);
Schema::ensure($pdo);
$service = new WatchlistService($pdo);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['remove_id'])) {
            $service->remove((int)$_POST['remove_id']);
        } else {
            $service->add((string)($_POST['market_key'] ?? ''), $_POST['label'] ?? null);
        }
        header('Location: /v2-watchlist.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = $service->all();
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function price($v): string { return $v === null ? '—' : number_format((float)$v, 2, ',', '.') . 'e'; }
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch V2 Watchlist</title>
<style>
:root{color-scheme:dark;--bg:#0d1015;--panel:#151a22;--line:#2a3340;--gold:#d6b56d;--text:#edf0f5;--muted:#9ca7b8;--buy:#65d69e;--sell:#f08b8b}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,sans-serif}.wrap{max-width:1180px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:24px}a{color:var(--gold);text-decoration:none}.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px}.form{display:grid;grid-template-columns:2fr 1fr auto;gap:10px}input,button{border-radius:9px;border:1px solid var(--line);background:#0f1319;color:var(--text);padding:11px 12px}button{cursor:pointer;background:#2a2418;border-color:#5d4a25;color:#f2d995}.grid{display:grid;gap:12px}.card{display:grid;grid-template-columns:2fr repeat(4,1fr) auto;gap:12px;align-items:center;background:#10151c;border:1px solid var(--line);border-radius:12px;padding:14px}.label{font-weight:700}.muted{color:var(--muted);font-size:13px}.buy{color:var(--buy)}.sell{color:var(--sell)}.danger{background:#2b1518;border-color:#68303a;color:#ffb7bf}@media(max-width:800px){.form,.card{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body><main class="wrap">
<div class="top"><div><h1>Watchlist</h1><div class="muted">V2.1 — volg je belangrijkste marktvarianten.</div></div><nav><a href="/v2.php">Dashboard</a> · <a href="/markets">Markten</a></nav></div>
<?php if($error): ?><div class="panel danger"><?= e($error) ?></div><?php endif; ?>
<section class="panel"><form method="post" class="form"><input name="market_key" required placeholder="market_key, bijvoorbeeld bone_dragon_staff|q9|inspiration_magic"><input name="label" placeholder="Optioneel label"><button type="submit">Toevoegen</button></form></section>
<section class="grid">
<?php if(!$rows): ?><div class="panel muted">Nog niets op je watchlist.</div><?php endif; ?>
<?php foreach($rows as $row): ?><article class="card"><div><div class="label"><?= e((string)$row['label']) ?></div><div class="muted"><?= e((string)$row['market_key']) ?><br>Laatste activiteit: <?= e((string)($row['last_activity'] ?? '—')) ?></div></div><div><span class="muted">WTB</span><br><strong class="buy"><?= price($row['best_wtb_ecto']) ?></strong></div><div><span class="muted">WTS</span><br><strong class="sell"><?= price($row['best_wts_ecto']) ?></strong></div><div><span class="muted">Buy offers</span><br><?= (int)$row['buy_offers'] ?></div><div><span class="muted">Sell offers</span><br><?= (int)$row['sell_offers'] ?></div><form method="post"><input type="hidden" name="remove_id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Verwijderen</button></form></article><?php endforeach; ?>
</section>
</main></body></html>
