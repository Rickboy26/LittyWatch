<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Schema.php';
require __DIR__ . '/app/V2/WatchlistService.php';
require __DIR__ . '/app/V2/Alerts/AlertService.php';

use LittyWatch\V2\Alerts\AlertService;
use LittyWatch\V2\Database;
use LittyWatch\V2\Schema;
use LittyWatch\V2\WatchlistService;

$pdo = Database::connect(__DIR__);
Schema::ensure($pdo);
$service = new WatchlistService($pdo);
$alerts = new AlertService($pdo);
$alerts->install();
$error = null;
$message = null;

function nullablePrice(mixed $value): ?float
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    return (float)str_replace(',', '.', $value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? 'save');
        if ($action === 'remove') {
            $marketKey = $service->remove((int)($_POST['id'] ?? 0));
            if ($marketKey !== null) {
                $alerts->removeWatchlistAlerts($marketKey);
            }
            $message = 'Item van de watchlist verwijderd.';
        } else {
            $marketKey = (string)($_POST['market_key'] ?? '');
            $label = (string)($_POST['label'] ?? '');
            $targetBuy = nullablePrice($_POST['target_buy_ecto'] ?? null);
            $targetSell = nullablePrice($_POST['target_sell_ecto'] ?? null);
            $service->save($marketKey, $label, $targetBuy, $targetSell);
            $alerts->syncWatchlistTargets($marketKey, $label, $targetBuy, $targetSell);
            $message = 'Watchlist en koersdoelen bijgewerkt.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = $service->all();
$options = $service->marketOptions('', 500);
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function price(mixed $value): string { return $value === null ? '—' : number_format((float)$value, 2, ',', '.') . 'e'; }
function targetClass(?float $current, ?float $target, string $direction): string {
    if ($current === null || $target === null || $current <= 0) return '';
    $hit = $direction === 'below' ? $current <= $target : $current >= $target;
    return $hit ? 'hit' : 'waiting';
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch V2.9 Watchlist</title>
<link rel="stylesheet" href="/assets/v2/platform.css">
<style>
:root{color-scheme:dark;--bg:#080b10;--panel:#121824;--panel2:#0e141d;--line:#293548;--gold:#d9b870;--text:#eef2f8;--muted:#9eabba;--green:#6bdba6;--red:#ef9191;--orange:#e7b56d}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 15% 0,#1a2435 0,#080b10 42%);color:var(--text);font:14px/1.5 Inter,system-ui,sans-serif}.wrap{max-width:1450px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.eyebrow{font-size:11px;color:var(--gold);font-weight:900;letter-spacing:.16em}h1{margin:4px 0}.muted{color:var(--muted)}nav a{color:var(--muted);text-decoration:none;margin-left:14px}.panel{background:rgba(18,24,36,.95);border:1px solid var(--line);border-radius:15px;overflow:hidden;margin-top:18px}.panel h2{margin:0;padding:16px 18px;border-bottom:1px solid var(--line)}.form{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;padding:17px}input,button{border-radius:9px;border:1px solid var(--line);background:var(--panel2);color:var(--text);padding:11px 12px}button{cursor:pointer;font-weight:800}.primary{background:linear-gradient(135deg,#735a2b,#b68d46)}.message{margin-top:16px;padding:12px 14px;border-radius:10px}.ok{background:rgba(107,219,166,.12);color:var(--green)}.error{background:rgba(239,145,145,.12);color:var(--red)}.table{overflow:auto}.row{min-width:1050px;display:grid;grid-template-columns:minmax(250px,1.6fr) 105px 105px 150px 150px 110px;gap:12px;align-items:center;padding:13px 17px;border-bottom:1px solid rgba(41,53,72,.7)}.row:last-child{border-bottom:0}.row strong{display:block}.market-link{color:inherit;text-decoration:none}.metric small{display:block;color:var(--muted)}.target{padding:7px 9px;border-radius:9px;background:#18202c}.target.hit{color:var(--green);background:rgba(107,219,166,.12)}.target.waiting{color:var(--orange);background:rgba(231,181,109,.1)}.danger{background:#2b1518;border-color:#68303a;color:#ffb7bf}.empty{padding:20px;color:var(--muted)}@media(max-width:1000px){.form{grid-template-columns:1fr 1fr}.form .wide{grid-column:1/-1}.top{flex-direction:column}}@media(max-width:650px){.wrap{padding:17px}.form{grid-template-columns:1fr}nav a{margin:0 12px 0 0}}
</style>
</head>
<body><main class="wrap">
<header class="top"><div><div class="eyebrow">LITTYWATCH V2.9</div><h1>Watchlist & koersdoelen</h1><div class="muted">Volg marktvarianten en maak automatisch een alert wanneer jouw koop- of verkoopdoel wordt geraakt.</div></div><nav><a href="/v2-hub.php">Command Center</a><a href="/v2-markets.php">Markten</a><a href="/v2-alerts.php">Alerts</a><a class="lw-nav-health" href="/v2-health.php">Systeem</a></nav></header>
<?php if ($message): ?><div class="message ok"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= h($error) ?></div><?php endif; ?>
<section class="panel"><h2>Markt volgen</h2><form method="post" class="form">
<input class="wide" name="market_key" list="markets" required placeholder="Zoek of plak een market_key">
<datalist id="markets"><?php foreach ($options as $option): ?><option value="<?= h($option['market_key']) ?>"><?= h($option['item']) ?> · WTB <?= h(price($option['best_wtb_ecto'])) ?> · WTS <?= h(price($option['best_wts_ecto'])) ?></option><?php endforeach; ?></datalist>
<input name="label" placeholder="Eigen label (optioneel)">
<input name="target_buy_ecto" inputmode="decimal" placeholder="Kopen bij max. ecto">
<input name="target_sell_ecto" inputmode="decimal" placeholder="Verkopen bij min. ecto">
<button class="primary" type="submit">Opslaan</button>
</form></section>
<section class="panel"><h2>Mijn watchlist</h2><div class="table">
<?php if ($rows === []): ?><div class="empty">Nog niets op je watchlist.</div><?php endif; ?>
<?php foreach ($rows as $row):
    $wtb = $row['best_wtb_ecto'] !== null ? (float)$row['best_wtb_ecto'] : null;
    $wts = $row['best_wts_ecto'] !== null ? (float)$row['best_wts_ecto'] : null;
    $buyTarget = $row['target_buy_ecto'] !== null ? (float)$row['target_buy_ecto'] : null;
    $sellTarget = $row['target_sell_ecto'] !== null ? (float)$row['target_sell_ecto'] : null;
?>
<div class="row">
<div><a class="market-link" href="/v2-market.php?key=<?= rawurlencode((string)$row['market_key']) ?>"><strong><?= h($row['label']) ?></strong><span class="muted"><?= h($row['market_key']) ?></span></a><div class="muted">Laatste activiteit: <?= h($row['last_activity'] ?? '—') ?></div></div>
<div class="metric"><small>Hoogste WTB</small><strong><?= h(price($wtb)) ?></strong></div>
<div class="metric"><small>Laagste WTS</small><strong><?= h(price($wts)) ?></strong></div>
<div class="target <?= h(targetClass($wts, $buyTarget, 'below')) ?>"><small>Koopdoel</small><br><strong><?= h(price($buyTarget)) ?></strong></div>
<div class="target <?= h(targetClass($wtb, $sellTarget, 'above')) ?>"><small>Verkoopdoel</small><br><strong><?= h(price($sellTarget)) ?></strong></div>
<form method="post"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Verwijderen</button></form>
</div><?php endforeach; ?>
</div></section>
</main></body></html>
