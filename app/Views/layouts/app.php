<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h((string)($title ?? 'LittyWatch')) ?></title>
<style>
:root{color-scheme:dark;--bg:#07101f;--panel:#111c31;--line:#293955;--muted:#8fa9d0;--text:#eef4ff;--blue:#2d6eea;--green:#09865d;--red:#b52836}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.45 system-ui,Segoe UI,Arial}.wrap{max-width:1380px;margin:auto;padding:26px}.top{display:flex;justify-content:space-between;gap:20px;align-items:center}.actions a,.btn{display:inline-block;padding:10px 14px;border-radius:8px;background:var(--blue);color:white;text-decoration:none;border:0;cursor:pointer}.btn.secondary{background:#243552}.btn:disabled{opacity:.55;cursor:wait}.statusline{margin-top:12px}.cards{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin:22px 0}.card,.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px}.card{padding:16px}.card b{font-size:25px;display:block}.muted{color:var(--muted)}.grid{display:grid;grid-template-columns:minmax(310px,.8fr) minmax(620px,1.7fr);gap:14px}.panel{padding:16px}.filters{display:flex;gap:8px;justify-content:flex-end}.filters input,.filters select{background:#0b1527;color:white;border:1px solid var(--line);border-radius:8px;padding:9px}.scroll{max-height:70vh;overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid var(--line);vertical-align:top}.badge{display:inline-block;border-radius:999px;padding:3px 8px;font-size:12px}.buy{background:var(--green)}.sell{background:var(--red)}code{white-space:pre-wrap;color:#b8d1ff}@media(max-width:980px){.cards{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
</style>
</head><body><main class="wrap"><?= $content ?></main></body></html>
