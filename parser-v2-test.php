<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$examples = [
    'WTS BDS q9 FC 35a|q11 Inspa 12a|',
    'WTS Eternal Shields: Q9 comm 70e, Q9 motivation 40e, Q10 tact 65e, Q10 comm 40e',
    'WTS ObsiEdge / EternalBlade / VoltaicSpear (all unidentified) in the package 22a',
    'WTS q9 15^50 OS shadow bow 35e | obsi shards 2:1e || WTB Ektos! 7=100k (5x) zkeys! 1.3e/ea',
    'WTS 250 GOTT 30a^Black Dye 1e/ea',
    'WTS q8/16 Tac (gold & inscr) Crude Shield 8a',
];

$input = trim((string)($_POST['message'] ?? ''));
if ($input === '') $input = $examples[0];
$offers = array_map(static fn($offer) => $offer->toArray(), parserV2()->parse($input));
?><!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch Parser v2 Lab</title>
<style>
body{margin:0;background:#0b1220;color:#e7eefc;font:15px system-ui,sans-serif}.wrap{max-width:1180px;margin:40px auto;padding:0 20px}textarea{width:100%;min-height:110px;background:#111b2e;color:#fff;border:1px solid #31405d;border-radius:10px;padding:14px;box-sizing:border-box}button{margin-top:10px;background:#2563eb;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:700}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;margin-top:24px}.card{background:#111b2e;border:1px solid #263653;border-radius:12px;padding:16px}.muted{color:#91a5c7}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:#1f3559;margin-right:5px}.accepted{background:#075d48}.review{background:#78520a}.rejected{background:#7c1d28}pre{white-space:pre-wrap;overflow:auto;background:#08101d;padding:12px;border-radius:8px;color:#cfe1ff}.examples a{color:#79aaff;display:block;margin:6px 0}
</style></head><body><main class="wrap">
<h1>LittyWatch Parser v2 Lab</h1><p class="muted">De v2-engine draait naast de bestaande v0.5-parser en schrijft nog niets naar de database.</p>
<form method="post"><textarea name="message"><?=h($input)?></textarea><button>Parseren met v2</button></form>
<div class="examples"><h3>Voorbeelden</h3><?php foreach($examples as $example): ?><a href="#" onclick="document.querySelector('textarea').value=<?=json_encode($example)?>;return false;"><?=h($example)?></a><?php endforeach; ?></div>
<div class="grid"><?php foreach($offers as $offer): ?><section class="card">
<div><span class="badge <?=h($offer['status'])?>"><?=h(strtoupper($offer['trade_type']))?></span><span class="badge <?=h($offer['status'])?>"><?=h($offer['status'])?></span></div>
<h2><?=h($offer['item'])?></h2><p class="muted"><?=h($offer['item_key'])?> · <?=number_format((float)$offer['confidence']*100,0)?>% · <?=h($offer['reason'])?></p>
<p><strong>Prijs:</strong> <?=h($offer['price']['amount']===null?'-':(string)$offer['price']['amount'])?><?=h($offer['price']['currency']??'')?> · basis <?=h($offer['price']['basis'])?> · unit <?=h($offer['price']['unit_ecto']===null?'-':number_format((float)$offer['price']['unit_ecto'],2).'e')?></p>
<p><strong>Profiel:</strong> <?=h($offer['profile']['name']??'Generic')?> · <code><?=h($offer['market_key']??'')?></code></p><p><strong>Relevante eigenschappen:</strong> <?=h(json_encode($offer['relevant_properties'],JSON_UNESCAPED_UNICODE))?></p><details><summary>Alle gevonden modifiers</summary><p><?=h(json_encode($offer['modifiers'],JSON_UNESCAPED_UNICODE))?></p></details><pre><?=h($offer['segment'])?></pre>
</section><?php endforeach; ?></div>
</main></body></html>
