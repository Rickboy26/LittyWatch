<?php declare(strict_types=1); $title='LittyWatch'; ?>
<div class="top">
  <div>
    <h1 style="margin:0">LittyWatch</h1>
    <div class="muted">v0.9 — live dashboard-API en automatische updates</div>
  </div>
  <div class="actions">
    <a href="collect.php">Nu ophalen</a>
    <a href="reparse.php">Opnieuw parseren</a>
    <a href="parser-v2-test.php">Parser v2 Lab</a>
    <button type="button" class="btn secondary" id="refreshButton">Vernieuwen</button>
    <button type="button" class="btn secondary" id="pauseButton">Pauzeren</button>
  </div>
</div>

<div class="statusline muted">Laatste dashboardupdate: <span id="lastUpdated">laden…</span> · nieuwste marktbericht: <span id="latestMessage"><?= h((string)($counters['latest_posted_at'] ?? '-')) ?></span></div>

<div class="cards" id="counterCards">
<?php foreach (['messages'=>'Berichten','offers'=>'Aanbiedingen','accepted'=>'Geaccepteerd','buy'=>'WTB','sell'=>'WTS','review'=>'Te controleren'] as $key=>$label): ?>
<div class="card" data-counter="<?= h($key) ?>"><span class="muted"><?= h($label) ?></span><b><?= (int)$counters[$key] ?></b></div>
<?php endforeach; ?>
</div>

<div class="grid">
<section class="panel">
  <h2>Flip-kansen</h2>
  <p class="muted">Mediaan van recente unieke traders. Minimaal twee kopers en twee verkopers.</p>
  <table><thead><tr><th>Item</th><th>WTS</th><th>WTB</th><th>Marge</th></tr></thead><tbody id="flipRows">
  <?php if (!$flips): ?><tr><td colspan="4" class="muted">Nog onvoldoende vergelijkbare WTB/WTS-prijzen.</td></tr><?php endif; ?>
  <?php foreach ($flips as $flip): ?><tr><td><?= h((string)$flip['item']) ?></td><td><?= number_format((float)$flip['sell_median'],2) ?>e</td><td><?= number_format((float)$flip['buy_median'],2) ?>e</td><td><?= number_format((float)$flip['spread'],2) ?>e</td></tr><?php endforeach; ?>
  </tbody></table>
</section>

<section class="panel">
  <div class="top">
    <h2>Herkende aanbiedingen</h2>
    <form class="filters" id="filterForm">
      <input name="q" value="<?= h($query) ?>" placeholder="Zoek item, speler of tekst">
      <select name="type"><option value="">Alles</option><option value="buy" <?= $type==='buy'?'selected':'' ?>>WTB</option><option value="sell" <?= $type==='sell'?'selected':'' ?>>WTS</option></select>
      <select name="status"><option value="">Alle kwaliteit</option><option value="accepted" <?= $status==='accepted'?'selected':'' ?>>Geaccepteerd</option><option value="review" <?= $status==='review'?'selected':'' ?>>Te controleren</option></select>
      <select name="limit"><option value="50" <?= $limit===50?'selected':'' ?>>50</option><option value="100" <?= $limit===100?'selected':'' ?>>100</option><option value="200" <?= $limit===200?'selected':'' ?>>200</option></select>
      <button class="btn">Zoeken</button>
    </form>
  </div>
  <div class="scroll"><table><thead><tr><th>Type</th><th>Item</th><th>Prijs</th><th>Speler / origineel</th></tr></thead><tbody id="offerRows">
  <?php foreach ($offers as $offer): ?>
  <tr><td><span class="badge <?= h((string)$offer['trade_type']) ?>"><?= strtoupper(h((string)$offer['trade_type'])) ?></span></td><td><strong><?= h((string)$offer['item']) ?></strong><?php if (!empty($offer['details'])): ?><div class="muted"><?= h((string)$offer['details']) ?></div><?php endif; ?><small class="muted"><?= (int)round((float)$offer['confidence']*100) ?>% · <?= h((string)($offer['quality_status'] ?? 'review')) ?></small></td><td><?= $offer['price_amount']!==null?h((string)$offer['price_amount']).h((string)$offer['price_currency']):'-' ?><?php if ($offer['unit_price_ecto']!==null): ?><div class="muted"><?= number_format((float)$offer['unit_price_ecto'],2) ?>e/stuk</div><?php endif; ?></td><td><?= h((string)$offer['player']) ?><div class="muted"><?= h((string)$offer['posted_at']) ?></div><code><?= h((string)($offer['raw_segment'] ?: $offer['message'])) ?></code></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</section>
</div>

<script>
(() => {
  const form = document.getElementById('filterForm');
  const offerRows = document.getElementById('offerRows');
  const flipRows = document.getElementById('flipRows');
  const refreshButton = document.getElementById('refreshButton');
  const pauseButton = document.getElementById('pauseButton');
  const lastUpdated = document.getElementById('lastUpdated');
  const latestMessage = document.getElementById('latestMessage');
  let paused = false;
  let busy = false;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
  const number = (value, decimals = 2) => Number(value).toLocaleString('nl-NL', {minimumFractionDigits: decimals, maximumFractionDigits: decimals});

  function params() {
    const data = new FormData(form);
    return new URLSearchParams([...data.entries()].filter(([,value]) => value !== ''));
  }

  function renderCounters(counters) {
    document.querySelectorAll('[data-counter]').forEach(card => {
      const key = card.dataset.counter;
      const target = card.querySelector('b');
      if (target && Object.prototype.hasOwnProperty.call(counters, key)) target.textContent = counters[key];
    });
    latestMessage.textContent = counters.latest_posted_at || '-';
  }

  function renderFlips(flips) {
    if (!flips.length) {
      flipRows.innerHTML = '<tr><td colspan="4" class="muted">Nog onvoldoende vergelijkbare WTB/WTS-prijzen.</td></tr>';
      return;
    }
    flipRows.innerHTML = flips.map(f => `<tr><td>${escapeHtml(f.item)}</td><td>${number(f.sell_median)}e</td><td>${number(f.buy_median)}e</td><td>${number(f.spread)}e</td></tr>`).join('');
  }

  function renderOffers(offers) {
    offerRows.innerHTML = offers.map(o => {
      const price = o.price_amount === null ? '-' : `${escapeHtml(o.price_amount)}${escapeHtml(o.price_currency)}`;
      const unit = o.unit_price_ecto === null ? '' : `<div class="muted">${number(o.unit_price_ecto)}e/stuk</div>`;
      const details = o.details ? `<div class="muted">${escapeHtml(o.details)}</div>` : '';
      const raw = o.raw_segment || o.message || '';
      return `<tr><td><span class="badge ${escapeHtml(o.trade_type)}">${escapeHtml(String(o.trade_type).toUpperCase())}</span></td><td><strong>${escapeHtml(o.item)}</strong>${details}<small class="muted">${Math.round(Number(o.confidence)*100)}% · ${escapeHtml(o.quality_status || 'review')}</small></td><td>${price}${unit}</td><td>${escapeHtml(o.player)}<div class="muted">${escapeHtml(o.posted_at)}</div><code>${escapeHtml(raw)}</code></td></tr>`;
    }).join('');
  }

  async function refresh() {
    if (paused || busy) return;
    busy = true;
    refreshButton.disabled = true;
    try {
      const response = await fetch(`api/dashboard?${params().toString()}`, {headers:{'Accept':'application/json'}, cache:'no-store'});
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const payload = await response.json();
      renderCounters(payload.data.counters);
      renderFlips(payload.data.flips || []);
      renderOffers(payload.data.offers || []);
      lastUpdated.textContent = new Date(payload.generated_at).toLocaleTimeString('nl-NL');
    } catch (error) {
      lastUpdated.textContent = `fout: ${error.message}`;
    } finally {
      busy = false;
      refreshButton.disabled = false;
    }
  }

  form.addEventListener('submit', event => { event.preventDefault(); refresh(); });
  refreshButton.addEventListener('click', refresh);
  pauseButton.addEventListener('click', () => {
    paused = !paused;
    pauseButton.textContent = paused ? 'Hervatten' : 'Pauzeren';
    if (!paused) refresh();
  });

  lastUpdated.textContent = new Date().toLocaleTimeString('nl-NL');
  window.setInterval(refresh, 30000);
})();
</script>
