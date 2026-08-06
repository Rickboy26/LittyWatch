<?php declare(strict_types=1);

$price = static function(mixed $value): string {
    return $value !== null ? number_format((float)$value, 2, ',', '.') . 'e' : '—';
};
$rawPrice = static function(?array $offer) use ($price): string {
    if ($offer === null) return 'Geen aanbod';
    if (($offer['price_basis'] ?? '') === 'barter' && !empty($offer['exchange_item'])) {
        $give = (float)($offer['exchange_give_quantity'] ?? 1);
        $receive = (float)($offer['exchange_receive_quantity'] ?? 1);
        return rtrim(rtrim(number_format($give, 2, '.', ''), '0'), '.') . ' ↔ '
            . rtrim(rtrim(number_format($receive, 2, '.', ''), '0'), '.') . ' '
            . (string)$offer['exchange_item'];
    }
    if ($offer['unit_price_ecto'] !== null) return $price($offer['unit_price_ecto']) . ' / stuk';
    if ($offer['price_amount'] !== null) return (string)$offer['price_amount'] . (string)$offer['price_currency'];
    return 'Geen geldprijs';
};
$tradeOffers = array_values(array_filter($offers, static fn(array $offer): bool => ($offer['trade_type'] ?? '') === 'trade'));
?>
<section class="item-detail-hero panel">
  <div class="item-identity">
    <img class="item-art" src="/item-image.php?item=<?= rawurlencode((string)$item['item']) ?>&size=192" alt="<?= h($item['item']) ?>">
    <div>
      <span class="kicker">GUILD WARS MARKTITEM</span>
      <h1><?= h($item['item']) ?></h1>
      <p>Prijsinformatie uit advertenties in Kamadan. Dit zijn vraag- en aanbodprijzen, geen bevestigde transacties.</p>
      <div class="item-meta">
        <span><?= (int)$item['offers'] ?> aanbiedingen</span>
        <span><?= (int)$analytics['unique_traders'] ?> unieke traders</span>
        <span>Laatst gezien: <?= h($item['latest_posted_at'] ?: 'onbekend') ?></span>
      </div>
    </div>
  </div>
  <div class="hero-actions">
    <a class="btn" href="/watchlist?item=<?= rawurlencode((string)$item['item']) ?>">☆ Watchlist</a>
    <a class="btn secondary" href="/items">← Alle items</a>
  </div>
</section>

<?php if ($itemKnowledge): ?>
<section class="item-knowledge-banner <?= (int)$itemKnowledge['is_unique'] === 1 ? 'unique' : 'standard' ?>">
  <div>
    <span class="kicker">ITEMKENNIS</span>
    <h2><?= h($itemKnowledge['item_name']) ?></h2>
    <p><?= h($itemKnowledge['wiki_extract'] ?: 'Lokale itemclassificatie beschikbaar.') ?></p>
  </div>
  <div class="knowledge-badges">
    <span><?= h(ucfirst($itemKnowledge['rarity'])) ?></span>
    <span><?= (int)$itemKnowledge['fixed_stats'] === 1 ? 'Vaste stats' : 'Variabele stats' ?></span>
    <span><?= (int)$itemKnowledge['modifiable'] === 1 ? 'Modificeerbaar' : 'Niet modificeerbaar' ?></span>
    <?php if ($itemKnowledge['wiki_url']): ?>
      <a href="<?= h($itemKnowledge['wiki_url']) ?>" target="_blank" rel="noopener">Guild Wars Wiki ↗</a>
    <?php endif; ?>
  </div>
  <?php if (!empty($itemKnowledge['canonical_stats'])): ?>
    <ul>
      <?php foreach ($itemKnowledge['canonical_stats'] as $stat): ?><li><?= h($stat) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="market-price-grid">
  <article class="price-card buy-card">
    <span class="price-label">Hoogste WTB</span>
    <strong><?= $price($item['highest_buy']) ?></strong>
    <small><?= $bestOffers['buy'] ? 'door ' . h($bestOffers['buy']['player']) : 'Nog geen bruikbare koopprijs' ?></small>
  </article>
  <article class="price-card sell-card">
    <span class="price-label">Laagste WTS</span>
    <strong><?= $price($item['lowest_sell']) ?></strong>
    <small><?= $bestOffers['sell'] ? 'door ' . h($bestOffers['sell']['player']) : 'Nog geen bruikbare verkoopprijs' ?></small>
  </article>
  <article class="price-card">
    <span class="price-label">Mediaan WTB</span>
    <strong><?= $price($analytics['buy_median']) ?></strong>
    <small><?= (int)$analytics['buy_count'] ?> prijsdatapunten</small>
  </article>
  <article class="price-card">
    <span class="price-label">Mediaan WTS</span>
    <strong><?= $price($analytics['sell_median']) ?></strong>
    <small><?= (int)$analytics['sell_count'] ?> prijsdatapunten</small>
  </article>
  <article class="price-card spread-card">
    <span class="price-label">Mediaan spread</span>
    <strong><?= $price($analytics['spread']) ?></strong>
    <small>WTB minus WTS</small>
  </article>
</section>

<section class="panel chartpanel">
  <div class="top">
    <div>
      <span class="kicker">PRIJSHISTORIE</span>
      <h2>Marktverloop</h2>
      <p class="muted">Chronologisch prijsverloop per stuk in ecto.</p>
    </div>
    <form class="filters" method="get" action="/item">
      <input type="hidden" name="name" value="<?= h($item['item']) ?>">
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
  <div class="chartlegend">
    <span><i class="dot buy-dot"></i> WTB</span>
    <span><i class="dot sell-dot"></i> WTS</span>
    <span><?= (int)$analytics['unique_traders'] ?> unieke traders</span>
  </div>
  <div id="priceChart" class="pricechart" aria-label="Prijsverloop"></div>
</section>

<div class="market-columns">
  <section class="panel offer-side buy-side">
    <div class="section-heading">
      <div><span class="kicker">KOPERS</span><h2>Actieve WTB</h2></div>
      <span class="count-pill"><?= count($buyOffers) ?></span>
    </div>
    <?php if (!$buyOffers): ?>
      <div class="empty-inline">Nog geen actieve koopadvertenties.</div>
    <?php else: ?>
      <div class="offer-list">
        <?php foreach ($buyOffers as $offer): ?>
          <article class="compact-offer">
            <div><strong><?= h($offer['player']) ?></strong><small><?= h($offer['posted_at']) ?></small></div>
            <div class="compact-price"><?= $rawPrice($offer) ?></div>
            <code><?= h($offer['raw_segment'] ?: $offer['message']) ?></code>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel offer-side sell-side">
    <div class="section-heading">
      <div><span class="kicker">VERKOPERS</span><h2>Actieve WTS</h2></div>
      <span class="count-pill"><?= count($sellOffers) ?></span>
    </div>
    <?php if (!$sellOffers): ?>
      <div class="empty-inline">Nog geen actieve verkoopadvertenties.</div>
    <?php else: ?>
      <div class="offer-list">
        <?php foreach ($sellOffers as $offer): ?>
          <article class="compact-offer">
            <div><strong><?= h($offer['player']) ?></strong><small><?= h($offer['posted_at']) ?></small></div>
            <div class="compact-price"><?= $rawPrice($offer) ?></div>
            <code><?= h($offer['raw_segment'] ?: $offer['message']) ?></code>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<div class="twocol">
  <section class="panel">
    <div class="section-heading"><div><span class="kicker">VARIANTEN</span><h2>Marktvarianten</h2></div></div>
    <div class="tablewrap"><table>
      <thead><tr><th>Variant</th><th>Offers</th><th>WTB</th><th>WTS</th><th>Gem. WTB</th><th>Gem. WTS</th></tr></thead>
      <tbody>
      <?php foreach($variants as $variant): ?>
        <tr>
          <td><a class="itemlink" href="/item?name=<?= rawurlencode((string)$item['item']) ?>&variant=<?= rawurlencode((string)$variant['variant']) ?>&scope=<?= h($scope) ?>"><?= h($variant['variant']) ?></a></td>
          <td><?= (int)$variant['offers'] ?></td>
          <td><?= (int)$variant['buy_count'] ?></td>
          <td><?= (int)$variant['sell_count'] ?></td>
          <td><?= $price($variant['avg_buy']) ?></td>
          <td><?= $price($variant['avg_sell']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <section class="panel">
    <span class="kicker">DATAKWALITEIT</span>
    <h2>Snelle beoordeling</h2>
    <div class="callout"><strong>Vraagzijde</strong><p><?= (int)$item['buy_count'] ?> koopadvertenties geregistreerd.</p></div>
    <div class="callout"><strong>Aanbodzijde</strong><p><?= (int)$item['sell_count'] ?> verkoopadvertenties geregistreerd.</p></div>
    <div class="callout"><strong>Controle</strong><p><?= (int)$item['review_count'] ?> aanbiedingen wachten nog op controle.</p></div>
  </section>
</div>


<?php if ($tradeOffers): ?>
<section class="panel">
  <div class="section-heading">
    <div><span class="kicker">RUILAANBIEDINGEN</span><h2>WTT voor dit item</h2></div>
    <span class="count-pill"><?= count($tradeOffers) ?></span>
  </div>
  <div class="offer-list">
    <?php foreach ($tradeOffers as $offer): ?>
      <article class="compact-offer trade-offer">
        <div><strong><?= h($offer['player']) ?></strong><small><?= h($offer['posted_at']) ?></small></div>
        <div class="compact-price"><?= h($rawPrice($offer)) ?></div>
        <code><?= h($offer['raw_segment'] ?: $offer['message']) ?></code>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<details class="panel raw-offers">
  <summary>Alle recente aanbiedingen tonen (<?= count($offers) ?>)</summary>
  <div class="tablewrap"><table>
    <thead><tr><th>Type</th><th>Variant</th><th>Prijs</th><th>Speler</th><th>Advertentie</th></tr></thead>
    <tbody>
    <?php foreach($offers as $offer): ?>
      <tr>
        <td><span class="badge <?= h($offer['trade_type']) ?>"><?= strtoupper(h($offer['trade_type'])) ?></span></td>
        <td><?= h($offer['details'] ?: 'Standaard') ?><div class="muted"><?= (int)round((float)$offer['confidence']*100) ?>% · <?= h($offer['quality_status']) ?></div></td>
        <td><?php if(($offer['price_basis']??'')==='barter'): ?><?=h($rawPrice($offer))?><?php else: ?><?= $offer['price_amount']!==null ? h($offer['price_amount']).h($offer['price_currency']) : '—' ?><?php if($offer['unit_price_ecto']!==null): ?><div class="muted"><?= $price($offer['unit_price_ecto']) ?>/stuk</div><?php endif; ?><?php endif; ?></td>
        <td><?= h($offer['player']) ?><div class="muted"><?= h($offer['posted_at']) ?></div></td>
        <td><code><?= h($offer['raw_segment'] ?: $offer['message']) ?></code></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</details>

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
  const groups={buy:[],sell:[]};
  points.forEach((p,i)=>{ if(groups[p.type]) groups[p.type].push([x(i),y(Number(p.price)),p]); });
  for(const type of ['buy','sell']){
    if(groups[type].length>1) svg += `<polyline points="${groups[type].map(v=>v[0]+','+v[1]).join(' ')}" class="chartline ${type}-line"/>`;
    for(const [cx,cy,p] of groups[type]) svg += `<circle cx="${cx}" cy="${cy}" r="5" class="chartpoint ${type}-point"><title>${esc(type.toUpperCase())}: ${Number(p.price).toFixed(2)}e · ${esc(p.player)}</title></circle>`;
  }
  svg += `<text x="${pad.l}" y="${height-12}" class="axislabel">ouder</text><text x="${width-pad.r}" y="${height-12}" text-anchor="end" class="axislabel">nieuwer</text></svg>`;
  target.innerHTML=svg;
})();
</script>
