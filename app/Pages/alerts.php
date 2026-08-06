<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/app/V2/Database.php';
require $root . '/app/V2/Intelligence/CurrencyFormatter.php';
require $root . '/app/V2/Alerts/AlertService.php';
require $root . '/app/V2/WatchlistService.php';
require $root . '/app/V2/Schema.php';

use LittyWatch\V2\Alerts\AlertService;
use LittyWatch\V2\Database;
use LittyWatch\V2\Intelligence\CurrencyFormatter;
use LittyWatch\V2\Schema;
use LittyWatch\V2\WatchlistService;

$pdo = Database::connect($root);
Schema::ensure($pdo);
$service = new AlertService($pdo);
$service->install();
$markets = (new WatchlistService($pdo))->marketOptions('', 500);
$money = new CurrencyFormatter($root);
$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? 'create');
        if ($action === 'toggle') $service->toggle((int)($_POST['id'] ?? 0));
        elseif ($action === 'delete') $service->delete((int)($_POST['id'] ?? 0));
        elseif ($action === 'evaluate') $result = $service->evaluate();
        elseif ($action === 'read') $service->markRead((int)($_POST['event_id'] ?? 0));
        elseif ($action === 'read_all') $service->markAllRead();
        else {
            $threshold = trim((string)($_POST['threshold_ecto'] ?? ''));
            $service->create(
                (string)($_POST['market_key'] ?? ''),
                (string)($_POST['label'] ?? ''),
                (string)($_POST['condition_type'] ?? ''),
                $threshold !== '' ? (float)str_replace(',', '.', $threshold) : null
            );
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$alerts = $service->all();
$events = $service->events(100);
$unread = $service->unreadCount();
function conditionLabel(string $type): string { return match ($type) {'wts_below'=>'WTS onder','wtb_above'=>'WTB boven','spread_above'=>'Spread boven','new_offer'=>'Nieuwe activiteit',default=>$type}; }
?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LittyWatch Alerts</title>
<link rel="stylesheet" href="/assets/v2/platform.css?v=310">
<style>
:root{color-scheme:dark;--bg:#080b10;--panel:#121824;--line:#293548;--text:#eef2f8;--muted:#9eabba;--gold:#d9b870;--green:#6bdba6;--red:#ef9191;--orange:#e7b56d}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 15% 0,#1a2435 0,#080b10 42%);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.wrap{max-width:1500px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;gap:20px}.eyebrow{font-size:11px;color:var(--gold);font-weight:900;letter-spacing:.16em}h1{margin:4px 0}.muted{color:var(--muted)}nav a{color:var(--muted);text-decoration:none;margin-left:14px}.grid{display:grid;grid-template-columns:.8fr 1.4fr;gap:16px;margin-top:22px}.panel{background:rgba(18,24,36,.95);border:1px solid var(--line);border-radius:15px;overflow:hidden}.panel h2{margin:0;padding:16px 18px;border-bottom:1px solid var(--line)}form.create{padding:17px;display:grid;gap:11px}input,select,button{width:100%;border:1px solid var(--line);border-radius:9px;background:#0e141d;color:var(--text);padding:10px 11px}button{cursor:pointer;font-weight:800}.primary{background:linear-gradient(135deg,#735a2b,#b68d46)}.error,.result{padding:11px;border-radius:9px;margin:10px 17px}.error{background:rgba(239,145,145,.12);color:var(--red)}.result{background:rgba(107,219,166,.12);color:var(--green)}.alert{display:grid;grid-template-columns:minmax(220px,1.5fr) 145px 110px 110px 150px;gap:10px;padding:12px 16px;border-bottom:1px solid rgba(41,53,72,.7);align-items:center}.badge{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:11px;font-weight:850}.on{color:var(--green);background:rgba(107,219,166,.12)}.off{color:var(--muted);background:#263244}.met{color:var(--orange);background:rgba(231,181,109,.12)}.actions{display:flex;gap:6px}.actions button{padding:7px}.event{display:grid;grid-template-columns:1fr auto;gap:12px;padding:12px 16px;border-bottom:1px solid rgba(41,53,72,.7)}.event.unread{background:rgba(217,184,112,.07);border-left:3px solid var(--gold)}.event small{color:var(--muted)}.head-actions{display:flex;gap:8px;padding:13px 16px;border-bottom:1px solid var(--line)}.head-actions form{flex:1}.empty{padding:18px;color:var(--muted)}@media(max-width:1000px){.grid{grid-template-columns:1fr}.top{flex-direction:column}}@media(max-width:700px){.wrap{padding:17px}.alert{grid-template-columns:1fr}.event{grid-template-columns:1fr}.head-actions{flex-direction:column}}
</style></head><body><main class="wrap">
<header class="top"><div><div class="eyebrow">LITTYWATCH</div><h1>Prijsalerts <span class="badge <?= $unread > 0 ? 'met' : 'off' ?>"><?= $unread ?> ongelezen</span></h1><div class="muted">Alerts gaan alleen af wanneer een conditie nieuw wordt geraakt of nieuwe marktactiviteit verschijnt.</div></div><nav><a href="/watchlist">Watchlist</a><a href="/markets">Markten</a><a href="/">Command Center</a><a class="lw-nav-health" href="/system">Systeem</a></nav></header>
<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
<?php if ($result): ?><div class="result"><?= (int)$result['checked'] ?> gecontroleerd · <?= (int)$result['triggered'] ?> nieuwe events · <?= (int)$result['reset'] ?> condities gereset</div><?php endif; ?>
<div class="grid"><section class="panel"><h2>Nieuwe losse alert</h2><form class="create" method="post">
<input name="label" placeholder="Naam voor deze alert"><input name="market_key" list="markets" required placeholder="Zoek of plak een market_key"><datalist id="markets"><?php foreach($markets as $market): ?><option value="<?= h($market['market_key']) ?>"><?= h($market['item']) ?></option><?php endforeach; ?></datalist>
<select name="condition_type"><option value="wts_below">WTS onder drempel</option><option value="wtb_above">WTB boven drempel</option><option value="spread_above">Spread boven drempel</option><option value="new_offer">Nieuwe marktactiviteit</option></select>
<input name="threshold_ecto" inputmode="decimal" placeholder="Drempel in ecto; leeg bij activiteit"><button class="primary">Alert opslaan</button></form></section>
<section class="panel"><h2>Mijn alerts</h2><div class="head-actions"><form method="post"><input type="hidden" name="action" value="evaluate"><button>Nu controleren</button></form><form method="post"><input type="hidden" name="action" value="read_all"><button>Alles gelezen</button></form></div>
<?php if($alerts===[]): ?><div class="empty">Nog geen alerts ingesteld.</div><?php endif; ?>
<?php foreach($alerts as $row): ?><div class="alert"><div><strong><?= h($row['label']!==''?$row['label']:($row['item']??$row['market_key'])) ?></strong><div class="muted"><?= h($row['market_key']) ?> · <?= h($row['source']) ?></div></div><div><?= h(conditionLabel((string)$row['condition_type'])) ?><?= $row['threshold_ecto']!==null?' '.h($money->ecto((float)$row['threshold_ecto'])):'' ?></div><div><span class="badge <?= (int)$row['is_enabled']===1?'on':'off' ?>"><?= (int)$row['is_enabled']===1?'Actief':'Pauze' ?></span></div><div><span class="badge <?= (int)$row['condition_met']===1?'met':'off' ?>"><?= (int)$row['condition_met']===1?'Doel geraakt':'Wachten' ?></span></div><div class="actions"><form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button>Toggle</button></form><form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button>Wissen</button></form></div></div><?php endforeach; ?>
</section></div>
<section class="panel" style="margin-top:16px"><h2>Recente alert-events</h2><?php if($events===[]): ?><div class="empty">Nog niets getriggerd.</div><?php endif; ?><?php foreach($events as $event): ?><div class="event <?= (int)$event['is_read']===0?'unread':'' ?>"><div><strong><?= h($event['message']) ?></strong><small><?= h($event['created_at']) ?> · <?= h($event['market_key']) ?></small></div><?php if((int)$event['is_read']===0): ?><form method="post"><input type="hidden" name="action" value="read"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><button>Gelezen</button></form><?php endif; ?></div><?php endforeach; ?></section>
</main><script src="/assets/v2/platform.js?v=310"></script>
</body></html>
