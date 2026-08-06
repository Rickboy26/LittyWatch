<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Encyclopedia/WikiClient.php';
require __DIR__ . '/app/V2/Encyclopedia/CatalogImportService.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Encyclopedia\CatalogImportService;
use LittyWatch\V2\Encyclopedia\WikiClient;

$pdo = Database::connect(__DIR__);
$service = new CatalogImportService($pdo, new WikiClient());
$service->install();

$result = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? 'import');
        if ($action === 'link') {
            $result = $service->linkToMarketCatalog();
        } else {
            $result = $service->importCategory(
                (string)($_POST['category'] ?? 'Category:Items'),
                isset($_POST['include_subcategories']),
                0,
                max(0, min(2, (int)($_POST['max_depth'] ?? 1))),
                max(1, min(25, (int)($_POST['max_pages'] ?? 10)))
            );
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = $service->summary();
$categories = $service->categories(500);
$query = trim((string)($_GET['q'] ?? ''));
$items = $service->items($query, 500);

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch Wiki Catalog Import</title>
<style>
:root{color-scheme:dark;--bg:#080b10;--panel:#121824;--line:#293548;--text:#eef2f8;--muted:#9eabba;--gold:#d9b870;--green:#6bdba6;--red:#ef9191}
*{box-sizing:border-box}body{margin:0;background:#080b10;color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.wrap{max-width:1450px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between}.eyebrow{font-size:11px;color:var(--gold);font-weight:900;letter-spacing:.16em}h1{margin:4px 0}.muted{color:var(--muted)}a{color:var(--muted)}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:11px;margin:20px 0}.card,.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px}.card{padding:15px}.card strong{display:block;font-size:23px}.grid{display:grid;grid-template-columns:.8fr 1.2fr;gap:16px}.panel{overflow:hidden}.panel h2{margin:0;padding:15px 17px;border-bottom:1px solid var(--line)}form.box{padding:16px;display:grid;gap:10px}input,select,button{border:1px solid var(--line);border-radius:9px;background:#0e141d;color:var(--text);padding:10px}button{cursor:pointer;font-weight:800}.primary{background:linear-gradient(135deg,#735a2b,#b68d46)}.message{margin:0 16px 16px;padding:11px;border-radius:9px}.ok{background:rgba(107,219,166,.12);color:var(--green)}.error{background:rgba(239,145,145,.12);color:var(--red)}.table{overflow:auto;max-height:650px}.row{min-width:720px;display:grid;grid-template-columns:minmax(240px,1.8fr) 150px 100px 100px;gap:10px;padding:10px 15px;border-bottom:1px solid rgba(41,53,72,.7)}.row:last-child{border-bottom:0}@media(max-width:900px){.grid{grid-template-columns:1fr}.stats{grid-template-columns:1fr 1fr}.wrap{padding:17px}}
</style>
</head>
<body><main class="wrap">
<header class="top"><div><div class="eyebrow">V2.7.1</div><h1>Guild Wars Wiki Catalog</h1><div class="muted">API met HTML-fallback; importeer beheerst per categorie.</div></div><div><a href="/v2-items.php">Item Encyclopedia</a></div></header>
<section class="stats">
<article class="card"><small>Categorieën</small><strong><?= (int)$summary['categories'] ?></strong></article>
<article class="card"><small>Wiki-items</small><strong><?= (int)$summary['items'] ?></strong></article>
<article class="card"><small>Gekoppeld</small><strong><?= (int)$summary['linked_items'] ?></strong></article>
<article class="card"><small>Wachtende categorieën</small><strong><?= (int)$summary['pending_categories'] ?></strong></article>
</section>
<div class="grid">
<section class="panel">
<h2>Categorie importeren</h2>
<form class="box" method="post">
<input type="hidden" name="action" value="import">
<input name="category" value="Category:Items" placeholder="Category:Items">
<label><input type="checkbox" name="include_subcategories" value="1"> Directe subcategorieën ook importeren</label>
<select name="max_depth"><option value="0">Alleen gekozen categorie</option><option value="1" selected>Één niveau diep</option><option value="2">Twee niveaus diep</option></select>
<input type="number" name="max_pages" min="1" max="25" value="10">
<button class="primary">Import starten</button>
</form>
<form class="box" method="post"><input type="hidden" name="action" value="link"><button>Wiki-items koppelen aan marktitems</button></form>
<?php if ($result): ?><div class="message ok"><pre><?= h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= h($error) ?></div><?php endif; ?>
</section>
<section class="panel"><h2>Ontdekte categorieën</h2><div class="table">
<?php foreach ($categories as $row): ?><div class="row"><div><?= h($row['category_title']) ?></div><div><?= h($row['parent_category'] ?: 'Hoofd') ?></div><div>diepte <?= (int)$row['depth'] ?></div><div><?= h($row['import_status']) ?></div></div><?php endforeach; ?>
</div></section>
</div>
<section class="panel" style="margin-top:16px"><h2>Ontdekte items</h2>
<form class="box" method="get"><input name="q" value="<?= h($query) ?>" placeholder="Zoek Wiki-item of categorie"><button>Zoeken</button></form>
<div class="table"><?php foreach ($items as $row): ?><div class="row"><div><?= h($row['wiki_title']) ?></div><div><?= h($row['source_category']) ?></div><div><?= h($row['import_status']) ?></div><div><?= h($row['linked_item_key'] ?: '—') ?></div></div><?php endforeach; ?></div>
</section>
</main></body></html>
