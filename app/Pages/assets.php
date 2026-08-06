<?php

declare(strict_types=1);

$root = dirname($root, 2);

require $root . '/app/V2/Database.php';
require $root . '/app/V2/Assets/AssetCatalogService.php';

use LittyWatch\V2\Assets\AssetCatalogService;
use LittyWatch\V2\Database;

function uploadError(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Het bestand is groter dan de PHP-uploadlimiet.',
        UPLOAD_ERR_PARTIAL => 'Het bestand is slechts gedeeltelijk geüpload.',
        UPLOAD_ERR_NO_FILE => 'Er is geen ZIP-bestand geselecteerd.',
        UPLOAD_ERR_NO_TMP_DIR => 'De tijdelijke uploadmap ontbreekt.',
        UPLOAD_ERR_CANT_WRITE => 'De server kon het uploadbestand niet opslaan.',
        UPLOAD_ERR_EXTENSION => 'Een PHP-extensie heeft de upload gestopt.',
        default => 'Onbekende uploadfout.',
    };
}

$pdo = Database::connect($root);
$service = new AssetCatalogService($pdo, $root);
$service->install();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'upload') {
            $file = $_FILES['asset_zip'] ?? null;
            if (!is_array($file)) throw new RuntimeException('Geen upload ontvangen.');
            $uploadCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadCode !== UPLOAD_ERR_OK) throw new RuntimeException(uploadError($uploadCode));
            $name = basename((string)($file['name'] ?? 'assets.zip'));
            if (!str_ends_with(strtolower($name), '.zip')) throw new RuntimeException('Alleen ZIP-bestanden worden geaccepteerd.');
            $result = $service->importZip((string)$file['tmp_name'], $name);
            $message = !empty($result['duplicate'])
                ? 'Dit assetpakket was al geïmporteerd.'
                : sprintf('%d iconen geïmporteerd; %d overgeslagen.', (int)$result['imported'], (int)$result['skipped']);
        } elseif ($action === 'server_import') {
            $package = (string)($_POST['package'] ?? '');
            $result = $service->importZip($service->serverPackagePath($package), $package);
            $message = !empty($result['duplicate'])
                ? 'Dit serverpakket was al geïmporteerd.'
                : sprintf('%d iconen geïmporteerd; %d overgeslagen.', (int)$result['imported'], (int)$result['skipped']);
        } elseif ($action === 'link') {
            $service->link((int)($_POST['asset_id'] ?? 0), (string)($_POST['item_key'] ?? ''));
            $message = 'Icoon gekoppeld aan marktitem.';
        } elseif ($action === 'unlink') {
            $service->unlink((int)($_POST['asset_id'] ?? 0));
            $message = 'Koppeling verwijderd.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$query = trim((string)($_GET['q'] ?? ''));
$filter = (string)($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all','linked','unlinked'], true)) $filter = 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 96;
$summary = $service->summary();
$assets = $service->assets($query, $filter, $perPage, ($page - 1) * $perPage);
$imports = $service->imports();
$marketItems = $service->marketItems('', 1500);
$packages = $service->serverPackages();
$uploadMax = ini_get('upload_max_filesize') ?: '?';
$postMax = ini_get('post_max_size') ?: '?';
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch  Game Assets</title>
<link rel="stylesheet" href="/assets/v2/platform.css?v=310">
<style>
:root{color-scheme:dark;--bg:#080b10;--panel:#121824;--line:#293548;--text:#eef2f8;--muted:#9eabba;--gold:#d9b870;--green:#6bdba6;--red:#ef9191}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 15% 0,#1b2738 0,#080b10 44%);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.wrap{max-width:1580px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;gap:20px}.eyebrow{font-size:11px;color:var(--gold);font-weight:900;letter-spacing:.16em}h1{margin:4px 0}.muted{color:var(--muted)}nav a{color:var(--muted);text-decoration:none;margin-left:14px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:11px;margin:20px 0}.card,.panel{background:rgba(18,24,36,.95);border:1px solid var(--line);border-radius:15px}.card{padding:15px}.card small{color:var(--muted)}.card strong{display:block;font-size:23px;margin-top:5px}.panel{padding:16px;margin-bottom:16px}.forms{display:grid;grid-template-columns:1fr 1fr;gap:16px}.form{display:grid;gap:10px}.form input,.form select,.form button,.toolbar input,.toolbar select,.toolbar button{border:1px solid var(--line);border-radius:10px;background:#0e141d;color:var(--text);padding:11px}.form button,.toolbar button{background:linear-gradient(135deg,#735a2b,#b68d46);font-weight:850;cursor:pointer}.message{padding:12px 14px;border-radius:10px;margin-bottom:16px}.ok{background:rgba(107,219,166,.12);color:var(--green)}.error{background:rgba(239,145,145,.12);color:var(--red)}.toolbar{display:flex;gap:8px;margin-bottom:16px}.toolbar input{flex:1}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(235px,1fr));gap:12px}.asset{padding:12px}.asset img{width:76px;height:76px;object-fit:contain;border:1px solid var(--line);border-radius:10px;background:#090e15;image-rendering:auto}.asset-head{display:flex;gap:12px;align-items:center}.asset strong{display:block}.asset small{color:var(--muted);display:block}.link-form{display:grid;grid-template-columns:1fr auto;gap:7px;margin-top:11px}.link-form input,.link-form button{min-width:0;border:1px solid var(--line);border-radius:8px;background:#0e141d;color:var(--text);padding:8px}.link-form button{background:#28384d;font-weight:800}.linked{color:var(--green);margin-top:9px}.imports{width:100%;border-collapse:collapse}.imports td,.imports th{padding:9px;border-bottom:1px solid var(--line);text-align:left}.pager{display:flex;justify-content:center;gap:10px;margin:20px}.pager a{color:var(--text);text-decoration:none;padding:9px 13px;border:1px solid var(--line);border-radius:9px}@media(max-width:800px){.wrap{padding:17px}.top{flex-direction:column}.stats,.forms{grid-template-columns:1fr 1fr}.toolbar{flex-direction:column}}@media(max-width:520px){.stats,.forms{grid-template-columns:1fr}}
</style>
</head>
<body><main class="wrap">
<header class="top"><div><div class="eyebrow">LITTYWATCH </div><h1>Guild Wars Asset Catalog</h1><div class="muted">Importeer originele Gw.dat-iconen en koppel ze aan bestaande marktitems.</div></div><nav><a href="/">Command Center</a><a href="/items">Items</a><a href="/system">Systeem</a></nav></header>
<section class="stats">
<article class="card"><small>Imports</small><strong><?= (int)$summary['imports'] ?></strong></article>
<article class="card"><small>Iconen</small><strong><?= number_format((int)$summary['assets'],0,',','.') ?></strong></article>
<article class="card"><small>Gekoppeld</small><strong><?= number_format((int)$summary['linked'],0,',','.') ?></strong></article>
<article class="card"><small>Nog te koppelen</small><strong><?= number_format((int)$summary['unlinked'],0,',','.') ?></strong></article>
</section>
<?php if ($message): ?><div class="message ok"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= h($error) ?></div><?php endif; ?>
<section class="panel forms">
<form class="form" method="post" enctype="multipart/form-data">
<strong>Asset-ZIP uploaden</strong><span class="muted">Serverlimieten: upload_max_filesize <?= h($uploadMax) ?> · post_max_size <?= h($postMax) ?></span>
<input type="hidden" name="action" value="upload"><input type="file" name="asset_zip" accept=".zip,application/zip" required><button>Import starten</button>
</form>
<form class="form" method="post">
<strong>ZIP vanaf server importeren</strong><span class="muted">Plaats grote bestanden via FTP in <code>imports/assets/</code>.</span>
<input type="hidden" name="action" value="server_import"><select name="package" required><option value="">Kies serverpakket</option><?php foreach($packages as $package):?><option><?= h($package) ?></option><?php endforeach;?></select><button>Serverpakket importeren</button>
</form>
</section>
<form class="toolbar" method="get"><input type="search" name="q" value="<?= h($query) ?>" placeholder="Zoek DAT-ID, bestandsnaam of gekoppeld item"><select name="filter"><option value="all" <?= $filter==='all'?'selected':'' ?>>Alles</option><option value="unlinked" <?= $filter==='unlinked'?'selected':'' ?>>Niet gekoppeld</option><option value="linked" <?= $filter==='linked'?'selected':'' ?>>Gekoppeld</option></select><button>Filteren</button></form>
<datalist id="market-items"><?php foreach($marketItems as $item):?><option value="<?= h($item['item_key']) ?>"><?= h($item['item']) ?></option><?php endforeach;?></datalist>
<section class="grid">
<?php foreach($assets as $asset):?>
<article class="card asset"><div class="asset-head"><img src="<?= h($asset['web_path']) ?>" alt=""><div><strong>DAT <?= h($asset['dat_file_id'] ?? '—') ?></strong><small><?= h($asset['source_filename']) ?></small><small><?= (int)$asset['width'] ?>×<?= (int)$asset['height'] ?> · <?= number_format((int)$asset['bytes']/1024,1,',','.') ?> KB</small></div></div>
<?php if (!empty($asset['linked_item_key'])): ?><div class="linked">✓ <?= h($asset['linked_item_name']) ?></div><form class="link-form" method="post"><input type="hidden" name="action" value="unlink"><input type="hidden" name="asset_id" value="<?= (int)$asset['id'] ?>"><span></span><button>Ontkoppelen</button></form>
<?php else: ?><form class="link-form" method="post"><input type="hidden" name="action" value="link"><input type="hidden" name="asset_id" value="<?= (int)$asset['id'] ?>"><input name="item_key" list="market-items" placeholder="Markt item_key" required><button>Koppelen</button></form><?php endif; ?>
</article>
<?php endforeach;?>
</section>
<div class="pager"><?php if($page>1):?><a href="?q=<?=rawurlencode($query)?>&filter=<?=h($filter)?>&page=<?=$page-1?>">← Vorige</a><?php endif;?><a href="?q=<?=rawurlencode($query)?>&filter=<?=h($filter)?>&page=<?=$page+1?>">Volgende →</a></div>
<?php if($imports):?><section class="panel"><h2>Recente imports</h2><div style="overflow:auto"><table class="imports"><tr><th>Bestand</th><th>Versie</th><th>Geïmporteerd</th><th>Overgeslagen</th><th>Datum</th></tr><?php foreach($imports as $import):?><tr><td><?=h($import['source_name'])?></td><td><?=h($import['extractor_version']?:'—')?></td><td><?=(int)$import['imported_icons']?></td><td><?=(int)$import['skipped_icons']?></td><td><?=h($import['created_at'])?></td></tr><?php endforeach;?></table></div></section><?php endif;?>
</main><script src="/assets/v2/platform.js?v=310"></script>
</body></html>
