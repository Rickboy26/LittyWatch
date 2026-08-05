<?php declare(strict_types=1); ?>
<div class="pagehead">
  <div><div class="eyebrow">ITEMOVERZICHT</div><h1><?= h((string)$item['item']) ?></h1><p class="muted">Prijsindicaties zijn gebaseerd op advertenties, niet op bevestigde transacties.</p></div>
  <a class="btn secondary" href="items">← Alle items</a>
</div>

<div class="statgrid">
  <div class="stat"><span>Aanbiedingen</span><b><?= (int)$item['offers'] ?></b></div>
  <div class="stat"><span>WTB</span><b><?= (int)$item['buy_count'] ?></b></div>
  <div class="stat"><span>WTS</span><b><?= (int)$item['sell_count'] ?></b></div>
  <div class="stat"><span>Mediaan WTB</span><b><?= $analytics['buy_median']!==null?number_format((float)$analytics['buy_median'],2,',','.').'e':'—' ?></b></div>
  <div class="stat"><span>Mediaan WTS</span><b><?= $analytics['sell_median']!==null?number_format((float)$analytics['sell_median'],2,',','.').'e':'—' ?></b></div>
  <div class="stat"><span>Mediaan spread</span><b><?= $analytics['spread']!==null?number_format((float)$analytics['spread'],2,',','.').'e':'—' ?></b></div>
</div>

<section class="panel chartpanel">
  <div class="top">
    <div><h2 style="margin-bottom:4px">Marktverloop</h2><p class="muted" style="margin-top:0">Prijs per stuk in ecto, chronologisch op basis van de laatst opgeslagen aanbiedingen.</p></div>
    <form class="filters" method="get" action="item">
      <input type="hidden" name="name" value="<?= h((string)$item['item']) ?>">
      <select name="variant">
        <option value="">Alle varianten</option>
        <?php foreach($variants as $variant): $v=(string)$variant['variant']; ?>
          <option value="<?= h($v) ?>" <?= $selectedVariant===$v?'selected':'' ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="scope">
        <option value="30" <?= $scope==='30'?'selected':'' ?>>Laatste 30</option>
        <option value="100" <?= $scope==='100'?'selected':'' ?>>Laatste 100</option>
        <option value="all" <?= $scope==='all'?'selected':'' ?>>Alles</option>
      </select>
      <button class="btn">Toepassen</button>
    </form>
  </div>
  <div class="chartlegend"><span><i class="dot buy-dot"></i> WTB</span><span><i class="dot sell-dot"></i> WTS</span><span><?= (int)$analytics['unique_traders'] ?> unieke traders</span></div>
  <div id="priceChart" class="pricechart" aria-label="Prijsverloop"></div>
  <div class="analyticsgrid">
    <div class="callout"><strong>WTB bereik</strong><p><?= $analytics['buy_min']!==null?number_format((float)$analytics['buy_min'],2,',','.').'e – '.number_format((float)$analytics['buy_max'],2,',','.').'e':'Geen prijsdata' ?></p></div>
    <div class="callout"><strong>WTS bereik</strong><p><?= $analytics['sell_min']!==null?number_format((float)$analytics['sell_min'],2,',','.').'e – '.number_format((float)$analytics['sell_max'],2,',','.').'e':'Geen prijsdata' ?></p></div>
    <div class="callout"><strong>Datapunten</strong><p><?= (int)$analytics['buy_count'] ?> WTB · <?= (int)$analytics['sell_count'] ?> WTS</p></div>
  </div>
</section>

<div class="twocol">
<section class="panel"><h2>Varianten</h2><div class="tablewrap"><table><thead><tr><th>Variant</th><th>Offers</th><th>WTB</th><th>WTS</th><th>Gem. WTB</th><th>Gem. WTS</th></tr></thead><tbody>
<?php foreach($variants as $variant): ?><tr><td><a class="itemlink" href="item?name=<?= rawurlencode((string)$item['item']) ?>&variant=<?= rawurlencode((string)$variant['variant']) ?>&scope=<?= h($scope) ?>"><?= h((string)$variant['variant']) ?></a></td><td><?= (int)$variant['offers'] ?></td><td><?= (int)$variant['buy_count'] ?></td><td><?= (int)$variant['sell_count'] ?></td><td><?= $variant['avg_buy']!==null?number_format((float)$variant['avg_buy'],2,',','.').'e':'—' ?></td><td><?= $variant['avg_sell']!==null?number_format((float)$variant['avg_sell'],2,',','.').'e':'—' ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="panel"><h2>Snelle beoordeling</h2><div class="callout"><strong>Vraagzijde</strong><p><?= (int)$item['buy_count'] ?> koopadvertenties geregistreerd.</p></div><div class="callout"><strong>Aanbodzijde</strong><p><?= (int)$item['sell_count'] ?> verkoopadvertenties geregistreerd.</p></div><div class="callout"><strong>Datakwaliteit</strong><p><?= (int)$item['review_count'] ?> aanbiedingen staan nog ter controle.</p></div></section>
</div>

<section class="panel"><h2>Recente aanbiedingen</h2><div class="tablewrap"><table><thead><tr><th>Type</th><th>Variant</th><th>Prijs</th><th>Speler</th><th>Advertentie</th></tr></thead><tbody>
<?php foreach($offers as $offer): ?><tr><td><span class="badge <?= h((string)$offer['trade_type']) ?>"><?= strtoupper(h((string)$offer['trade_type'])) ?></span></td><td><?= h((string)($offer['details'] ?: 'Standaard')) ?><div class="muted"><?= (int)round((float)$offer['confidence']*100) ?>% · <?= h((string)$offer['quality_status']) ?></div></td><td><?= $offer['price_amount']!==null?h((string)$offer['price_amount']).h((string)$offer['price_currency']):'—' ?><?php if($offer['unit_price_ecto']!==null): ?><div class="muted"><?= number_format((float)$offer['unit_price_ecto'],2,',','.') ?>e/stuk</div><?php endif; ?></td><td><?= h((string)$offer['player']) ?><div class="muted"><?= h((string)$offer['posted_at']) ?></div></td><td><code><?= h((string)($offer['raw_segment'] ?: $offer['message'])) ?></code></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<script>
(() => {
  const points = <?= json_encode($analytics['points'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  const target = document.getElementById('priceChart');
  if (!points.length) {
    target.innerHTML = '<div class="emptychart">Nog geen bruikbare prijsdata voor deze selectie.</div>';
    return;
  }
  const width = 1100, height = 330, pad = {l:60,r:24,t:22,b:42};
  const values = points.map(p => Number(p.price)).filter(Number.isFinite);
  let min = Math.min(...values), max = Math.max(...values);
  if (min === max) { min *= .9; max *= 1.1; if (min === max) { min=0; max=1; } }
  const range = max-min;
  min = Math.max(0,min-range*.08); max += range*.08;
  const x = i => pad.l + (points.length===1 ? (width-pad.l-pad.r)/2 : i*(width-pad.l-pad.r)/(points.length-1));
  const y = v => pad.t + (max-v)*(height-pad.t-pad.b)/(max-min);
  const esc = s => String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  let svg = `<svg viewBox="0 0 ${width} ${height}" role="img">`;
  for(let i=0;i<=4;i++){
    const value=min+(max-min)*(4-i)/4, yy=pad.t+i*(height-pad.t-pad.b)/4;
    svg += `<line x1="${pad.l}" y1="${yy}" x2="${width-pad.r}" y2="${yy}" class="gridline"/><text x="${pad.l-10}" y="${yy+4}" text-anchor="end" class="axislabel">${value.toFixed(1)}e</text>`;
  }
  const groups={buy:[],sell:[]}; points.forEach((p,i)=>{ if(groups[p.type]) groups[p.type].push([x(i),y(Number(p.price)),p]); });
  for(const type of ['buy','sell']){
    if(groups[type].length>1) svg += `<polyline points="${groups[type].map(v=>v[0]+','+v[1]).join(' ')}" class="chartline ${type}-line"/>`;
    for(const [cx,cy,p] of groups[type]) svg += `<circle cx="${cx}" cy="${cy}" r="5" class="chartpoint ${type}-point"><title>${esc(type.toUpperCase())}: ${Number(p.price).toFixed(2)}e · ${esc(p.player)}</title></circle>`;
  }
  svg += `<text x="${pad.l}" y="${height-12}" class="axislabel">ouder</text><text x="${width-pad.r}" y="${height-12}" text-anchor="end" class="axislabel">nieuwer</text></svg>`;
  target.innerHTML=svg;
})();
</script>
