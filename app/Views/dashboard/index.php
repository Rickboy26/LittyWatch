<?php declare(strict_types=1); $title='Dashboard · LittyWatch'; ?>
<section class="page-intro"><div><span class="kicker">KAMADAN MARKET INTELLIGENCE</span><h1>Guild Wars handel, helder in beeld.</h1><p>Bekijk actuele advertenties, betaalverhoudingen en betrouwbare marktvarianten zonder door eindeloze tradechat te zoeken.</p></div><div class="actions"><a href="/markets">Bekijk markten</a><a class="btn secondary" href="/admin">Beheer</a></div></section>
<section class="exchange-panel" aria-labelledby="exchange-title"><div class="exchange-head"><div><span class="kicker">STANDAARD BETAALMETHODES</span><h2 id="exchange-title">Exchange rates</h2></div><div class="muted">Bijgewerkt: <?=h((string)($exchangeRates['updated_at']??'-'))?></div></div><div class="exchange-grid">
<?php $imageMap=['Gold ↔ Ecto'=>'Glob of Ectoplasm','Ecto ↔ Armbrace'=>'Armbrace of Truth','Ecto ↔ Zaishen Key'=>'Zaishen Key','Ecto ↔ Obsidian Shard'=>'Obsidian Shard']; foreach(($exchangeRates['rates']??[]) as $rate): $img=$imageMap[$rate['label']]??$rate['right_unit']; ?>
<article class="exchange-card"><img src="/item-image.php?item=<?=rawurlencode((string)$img)?>&size=64" alt=""><div><span class="label"><?=h((string)$rate['label'])?></span><div class="exchange-values"><strong><?=number_format((float)$rate['left_amount'],abs((float)$rate['left_amount']-round((float)$rate['left_amount']))>.000001?2:0,',','.')?> <?=h((string)$rate['left_unit'])?></strong><span class="exchange-equals">=</span><strong><?=number_format((float)$rate['right_amount'],abs((float)$rate['right_amount']-round((float)$rate['right_amount']))>.000001?2:0,',','.')?> <?=h((string)$rate['right_unit'])?></strong></div></div></article><?php endforeach; ?>
</div><p class="exchange-note">Indicatieve verhoudingen uit <code>config/exchange-rates.php</code>. Automatische marktkoersen volgen later.</p></section>
<div class="metric-grid" id="counterCards"><?php foreach(['messages'=>'Berichten','offers'=>'Aanbiedingen','accepted'=>'Geaccepteerd','buy'=>'WTB','sell'=>'WTS','review'=>'Review'] as$key=>$label):?><article class="metric"><span><?=h($label)?></span><strong data-counter="<?=h($key)?>"><?=(int)($counters[$key]??0)?></strong></article><?php endforeach;?></div>
<div class="hero-grid"><section class="surface"><div class="section-heading"><div><span class="kicker">LIVE FEED</span><h2>Nieuwste aanbiedingen</h2></div><div class="actions"><a class="btn secondary" href="/markets">Alle markten</a></div></div><div class="tablewrap"><table><thead><tr><th>Type</th><th>Item</th><th>Prijs</th><th>Speler</th></tr></thead><tbody><?php foreach(array_slice($offers,0,18) as$o):?><tr><td><span class="badge <?=h($o['trade_type'])?>"><?=strtoupper(h($o['trade_type']))?></span></td><td><div class="item-cell"><img class="item-thumb" src="/item-image.php?item=<?=rawurlencode((string)$o['item'])?>&size=48" alt=""><div><a class="itemlink" href="/item?name=<?=rawurlencode((string)$o['item'])?>"><?=h($o['item'])?></a><div class="muted"><?=h((string)($o['details']??''))?></div></div></div></td><td><div class="price-pair"><?php if(($o['price_basis']??'')==='barter' && !empty($o['exchange_item'])): ?><strong><?=h((string)($o['exchange_give_quantity']??1))?> : <?=h((string)($o['exchange_receive_quantity']??1))?></strong><small>voor <?=h($o['exchange_item'])?></small><?php else: ?><strong><?=$o['price_amount']!==null?h((string)$o['price_amount'].$o['price_currency']):'—'?></strong><?php if($o['unit_price_ecto']!==null):?><small><?=number_format((float)$o['unit_price_ecto'],2,',','.')?>e/stuk</small><?php endif;?><?php endif;?></div></td><td><?=h($o['player'])?><div class="muted"><?=h(lw_local_datetime($o['posted_at']))?></div></td></tr><?php endforeach;?></tbody></table></div></section>
<aside><section class="surface"><div class="section-heading"><div><span class="kicker">SPREADS</span><h2>Flip-kansen</h2></div></div><?php if(!$flips):?><p class="muted">Nog onvoldoende vergelijkbare WTB/WTS-prijzen.</p><?php else:?><?php foreach(array_slice($flips,0,8) as$f):?><div class="callout"><strong><?=h($f['item'])?></strong><p>WTS <?=number_format((float)$f['sell'],2,',','.')?>e · WTB <?=number_format((float)$f['buy'],2,',','.')?>e</p></div><?php endforeach;?><?php endif;?></section><section class="surface"><span class="kicker">STATUS</span><h2>Dataverwerking</h2><p class="muted">Dashboard wordt automatisch ververst. De collector draait apart via cron of handmatig vanuit Beheer.</p><a class="btn secondary" href="/admin">Open beheer</a></section></aside></div>
<script>
(() => {
  const refresh = async () => {
    try {
      const response = await fetch('/api/dashboard?limit=20', {headers:{Accept:'application/json'}, cache:'no-store'});
      if (!response.ok) return;
      const payload = await response.json();
      const counters = payload?.data?.counters || {};
      document.querySelectorAll('[data-counter]').forEach(node => {
        const key = node.dataset.counter;
        if (Object.prototype.hasOwnProperty.call(counters, key)) node.textContent = Number(counters[key] || 0).toLocaleString('nl-NL');
      });
    } catch (_) {}
  };
  window.setInterval(refresh, 30000);
})();
</script>
