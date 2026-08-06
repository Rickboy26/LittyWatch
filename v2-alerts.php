<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Intelligence/CurrencyFormatter.php';
require __DIR__ . '/app/V2/Alerts/AlertService.php';

use LittyWatch\V2\Alerts\AlertService;
use LittyWatch\V2\Database;
use LittyWatch\V2\Intelligence\CurrencyFormatter;

$pdo = Database::connect(__DIR__);
$service = new AlertService($pdo);
$service->install();
$money = new CurrencyFormatter(__DIR__);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? 'create');
        if ($action === 'toggle') {
            $service->toggle((int)($_POST['id'] ?? 0));
        } elseif ($action === 'delete') {
            $service->delete((int)($_POST['id'] ?? 0));
        } elseif ($action === 'evaluate') {
            $service->evaluate();
        } else {
            $threshold = trim((string)($_POST['threshold_ecto'] ?? ''));
            $service->create(
                (string)($_POST['market_key'] ?? ''),
                (string)($_POST['label'] ?? ''),
                (string)($_POST['condition_type'] ?? ''),
                $threshold !== '' ? (float)str_replace(',', '.', $threshold) : null
            );
        }
        header('Location: /v2-alerts.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$alerts = $service->all();
$events = $service->events(75);

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function conditionLabel(string $type): string {
    return match ($type) {
        'wts_below' => 'WTS onder',
        'wtb_above' => 'WTB boven',
        'spread_above' => 'Spread boven',
        'new_offer' => 'Nieuwe activiteit',
        default => $type,
    };
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch V2.5 Alerts</title>
<style>
:root{color-scheme:dark;--bg:#080b10;--panel:#121824;--line:#293548;--text:#eef2f8;--muted:#9eabba;--gold:#d9b870;--green:#6bdba6;--red:#ef9191}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 15% 0,#1a2435 0,#080b10 42%);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.wrap{max-width:1450px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;gap:20px}.eyebrow{font-size:11px;color:var(--gold);font-weight:900;letter-spacing:.16em}h1{margin:4px 0}.muted{color:var(--muted)}nav a{color:var(--muted);text-decoration:none;margin-left:14px}.grid{display:grid;grid-template-columns:.8fr 1.4fr;gap:16px;margin-top:22px}.panel{background:rgba(18,24,36,.95);border:1px solid var(--line);border-radius:15px;overflow:hidden}.panel h2{margin:0;padding:16px 18px;border-bottom:1px solid var(--line)}form.create{padding:17px;display:grid;gap:11px}input,select,button{width:100%;border:1px solid var(--line);border-radius:9px;background:#0e141d;color:var(--text);padding:10px 11px}button{cursor:pointer;font-weight:800}.primary{background:linear-gradient(135deg,#735a2b,#b68d46)}.error{padding:11px;background:rgba(239,145,145,.12);color:var(--red);border-radius:9px}.alert{display:grid;grid-template-columns:minmax(220px,1.6fr) 135px 120px 150px;gap:10px;padding:12px 16px;border-bottom:1px solid rgba(41,53,72,.7);align-items:center}.alert:last-child{border-bottom:0}.badge{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:11px;font-weight:850}.on{color:var(--green);background:rgba(107,219,166,.12)}.off{color:var(--muted);background:#263244}.actions{display:flex;gap:6px}.actions button{padding:7px}.event{padding:11px 16px;border-bottom:1px solid rgba(41,53,72,.7)}.event:last-child{border-bottom:0}.event strong{display:block}.event small{color:var(--muted)}.head-actions{display:flex;gap:8px;padding:13px 16px;border-bottom:1px solid var(--line)}@media(max-width:950px){.grid{grid-template-columns:1fr}.top{flex-direction:column}}@media(max-width:600px){.wrap{padding:17px}.alert{grid-template-columns:1fr}}
</style>
</head>
<body><main class="wrap">
<header class="top"><div><div class="eyebrow">LITTYWATCH V2.5</div><h1>Prijsalerts</h1><div class="muted">Website-alerts; Discord en browsermeldingen volgen later.</div></div><nav><a href="/v2-live.php">Live feed</a><a href="/v2-markets.php">Markten</a><a href="/v2.php">Dashboard</a></nav></header>
<div class="grid">
<section class="panel">
<h2>Nieuwe alert</h2>
<form class="create" method="post">
<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
<input name="label" placeholder="Naam, bijvoorbeeld BDS q9 Insp goedkoop">
<input name="market_key" required placeholder="market_key uit Market Explorer">
<select name="condition_type">
<option value="wts_below">WTS onder drempel</option>
<option value="wtb_above">WTB boven drempel</option>
<option value="spread_above">Spread boven drempel</option>
<option value="new_offer">Nieuwe marktactiviteit</option>
</select>
<input name="threshold_ecto" inputmode="decimal" placeholder="Drempel in ecto; leeg bij nieuwe activiteit">
<button class="primary">Alert opslaan</button>
</form>
</section>

<section class="panel">
<h2>Mijn alerts</h2>
<form class="head-actions" method="post"><input type="hidden" name="action" value="evaluate"><button>Nu controleren</button></form>
<?php if ($alerts === []): ?><div style="padding:18px" class="muted">Nog geen alerts ingesteld.</div><?php endif; ?>
<?php foreach ($alerts as $row): ?>
<div class="alert">
<div><strong><?= h($row['label'] !== '' ? $row['label'] : ($row['item'] ?? $row['market_key'])) ?></strong><div class="muted"><?= h($row['market_key']) ?></div></div>
<div><?= h(conditionLabel((string)$row['condition_type'])) ?><?= $row['threshold_ecto'] !== null ? ' ' . h($money->ecto((float)$row['threshold_ecto'])) : '' ?></div>
<div><span class="badge <?= (int)$row['is_enabled'] === 1 ? 'on' : 'off' ?>"><?= (int)$row['is_enabled'] === 1 ? 'Actief' : 'Pauze' ?></span></div>
<div class="actions">
<form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button>Toggle</button></form>
<form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button>Wissen</button></form>
</div>
</div>
<?php endforeach; ?>
</section>
</div>

<section class="panel" style="margin-top:16px">
<h2>Recente alert-events</h2>
<?php if ($events === []): ?><div style="padding:18px" class="muted">Nog niets getriggerd.</div><?php endif; ?>
<?php foreach ($events as $event): ?><div class="event"><strong><?= h($event['message']) ?></strong><small><?= h($event['created_at']) ?> · <?= h($event['market_key']) ?></small></div><?php endforeach; ?>
</section>
</main></body></html>
