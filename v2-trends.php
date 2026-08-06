<?php

declare(strict_types=1);

require __DIR__ . '/app/V2/Database.php';
require __DIR__ . '/app/V2/Schema.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Schema;

$pdo = Database::connect(__DIR__);
Schema::ensure($pdo);

$sql = <<<SQL
WITH ranked AS (
    SELECT
        market_key,
        captured_at,
        median_wtb_ecto,
        median_wts_ecto,
        active_offers,
        unique_traders,
        ROW_NUMBER() OVER (PARTITION BY market_key ORDER BY captured_at DESC) AS rn_desc,
        ROW_NUMBER() OVER (PARTITION BY market_key ORDER BY captured_at ASC) AS rn_asc
    FROM market_snapshots
    WHERE captured_at >= datetime('now', '-7 days')
), latest AS (
    SELECT * FROM ranked WHERE rn_desc = 1
), earliest AS (
    SELECT * FROM ranked WHERE rn_asc = 1
)
SELECT
    l.market_key,
    l.median_wtb_ecto,
    l.median_wts_ecto,
    l.active_offers,
    l.unique_traders,
    l.captured_at,
    CASE WHEN e.median_wts_ecto > 0 AND l.median_wts_ecto > 0
         THEN ((l.median_wts_ecto - e.median_wts_ecto) / e.median_wts_ecto) * 100
         ELSE NULL END AS wts_change_pct
FROM latest l
JOIN earliest e ON e.market_key = l.market_key
ORDER BY ABS(COALESCE(wts_change_pct, 0)) DESC, l.active_offers DESC
LIMIT 100
SQL;
$rows = $pdo->query($sql)->fetchAll();
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function p($v): string { return $v === null ? '—' : number_format((float)$v, 2, ',', '.') . 'e'; }
?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LittyWatch V2 Trends</title><style>
:root{color-scheme:dark;--bg:#0d1015;--panel:#151a22;--line:#2a3340;--gold:#d6b56d;--text:#edf0f5;--muted:#9ca7b8;--up:#65d69e;--down:#f08b8b}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,sans-serif}.wrap{max-width:1180px;margin:auto;padding:28px}a{color:var(--gold);text-decoration:none}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px;overflow:hidden}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid var(--line)}th{color:var(--muted);font-size:12px;text-transform:uppercase}.up{color:var(--up)}.down{color:var(--down)}.muted{color:var(--muted)}@media(max-width:760px){.panel{overflow:auto}.top{align-items:flex-start;gap:12px;flex-direction:column}}
</style></head><body><main class="wrap"><div class="top"><div><h1>Markttrends</h1><div class="muted">V2.1 — vergelijking van eerste en laatste snapshot binnen 7 dagen.</div></div><nav><a href="/v2.php">Dashboard</a> · <a href="/v2-watchlist.php">Watchlist</a> · <a href="/v2-snapshot.php">Snapshot maken</a></nav></div><section class="panel"><table><thead><tr><th>Markt</th><th>Mediaan WTB</th><th>Mediaan WTS</th><th>WTS-beweging</th><th>Offers</th><th>Traders</th><th>Snapshot</th></tr></thead><tbody><?php if(!$rows): ?><tr><td colspan="7" class="muted">Nog onvoldoende snapshots. Open eerst v2-snapshot.php, en herhaal dit later.</td></tr><?php endif; ?><?php foreach($rows as $row): $change=$row['wts_change_pct']; ?><tr><td><a href="/market?key=<?= urlencode((string)$row['market_key']) ?>"><?= e((string)$row['market_key']) ?></a></td><td><?= p($row['median_wtb_ecto']) ?></td><td><?= p($row['median_wts_ecto']) ?></td><td class="<?= $change===null?'':((float)$change>=0?'up':'down') ?>"><?= $change===null?'—':number_format((float)$change,1,',','.').'%' ?></td><td><?= (int)$row['active_offers'] ?></td><td><?= (int)$row['unique_traders'] ?></td><td class="muted"><?= e((string)$row['captured_at']) ?></td></tr><?php endforeach; ?></tbody></table></section></main></body></html>
