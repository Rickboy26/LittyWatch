<?php
declare(strict_types=1);
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function price(array $offer): string {
    if ($offer['price_amount'] === null || $offer['price_amount'] === '') return 'PM offer';
    return rtrim(rtrim(number_format((float)$offer['price_amount'], 2, ',', '.'), '0'), ',') . h($offer['price_currency'] ?? '');
}
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LittyWatch V2</title>
<link rel="stylesheet" href="/assets/v2/app.css?v=2.0">
</head>
<body>
<div class="shell">
<aside class="sidebar">
  <div class="brand"><span class="brand-mark">L</span><div><strong>LittyWatch</strong><small>Guild Wars Market Intelligence</small></div></div>
  <nav>
    <a class="active" href="/">Overzicht</a>
    <a href="/markets">Markten</a>
    <a href="/items">Items</a>
    <a href="/parser-review">Parser Review</a>
    <a href="/admin">Beheer</a>
  </nav>
  <div class="sidebar-note">V2 foundation<br><span>Veilig naast de huidige site</span></div>
</aside>
<main>
<header class="topbar">
  <div><p class="eyebrow">MARKET TERMINAL</p><h1>Goedemorgen, Litty</h1><p class="sub">Een rustige, bruikbare basis voor LittyWatch v2.</p></div>
  <div class="status"><span></span> Shadow mode actief</div>
</header>

<section class="rate-grid">
<?php foreach ($rates as $rate): ?>
  <article class="rate-card"><div class="coin"><?= h(strtoupper(substr($rate['icon'],0,1))) ?></div><div><small>Wisselkoers</small><strong><?= h($rate['left']) ?></strong><span>= <?= h($rate['right']) ?></span></div></article>
<?php endforeach; ?>
</section>

<section class="stat-grid">
<?php foreach ([['Berichten',$stats['messages']],['Aanbiedingen',$stats['offers']],['Structured',$stats['structured']],['Markten',$stats['markets']],['Watchlist',$stats['watchlist']]] as [$label,$value]): ?>
  <article class="stat-card"><small><?= h($label) ?></small><strong><?= number_format((int)$value,0,',','.') ?></strong></article>
<?php endforeach; ?>
</section>

<div class="content-grid">
<section class="panel wide">
  <div class="panel-head"><div><p class="eyebrow">ACTIVITEIT</p><h2>Populaire marktvarianten</h2></div><a href="/markets">Alles bekijken</a></div>
  <div class="market-list">
  <?php if (!$markets): ?><div class="empty">Nog geen structured markets. Draai later de v2 reparse.</div><?php endif; ?>
  <?php foreach ($markets as $market): ?>
    <a class="market-row" href="/market?key=<?= rawurlencode((string)$market['market_key']) ?>">
      <div class="item-avatar"><?= h(strtoupper(substr((string)$market['item'],0,1))) ?></div>
      <div class="market-main"><strong><?= h($market['item']) ?></strong><code><?= h($market['market_key']) ?></code></div>
      <div class="metric"><small>Offers</small><b><?= (int)$market['offers'] ?></b></div>
      <div class="metric"><small>Traders</small><b><?= (int)$market['traders'] ?></b></div>
      <span class="chev">›</span>
    </a>
  <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <div class="panel-head"><div><p class="eyebrow">ROADMAP</p><h2>V2 modules</h2></div></div>
  <ul class="roadmap">
    <?php foreach ($features as $feature => $enabled): ?>
      <li><span class="dot <?= $enabled ? 'on' : '' ?>"></span><span><?= h(ucwords(str_replace('_',' ',$feature))) ?></span><em><?= $enabled ? 'klaar' : 'gepland' ?></em></li>
    <?php endforeach; ?>
  </ul>
</section>
</div>

<section class="panel">
  <div class="panel-head"><div><p class="eyebrow">LIVE FEED</p><h2>Nieuwste aanbiedingen</h2></div></div>
  <div class="offer-table">
    <div class="tr th"><span>Type</span><span>Item</span><span>Variant</span><span>Prijs</span><span>Speler</span></div>
    <?php foreach ($offers as $offer): ?>
      <div class="tr"><span><b class="pill <?= h(strtolower((string)$offer['trade_type'])) ?>"><?= h(strtoupper((string)$offer['trade_type'])) ?></b></span><span><strong><?= h($offer['item']) ?></strong></span><span><?= h(trim(($offer['requirement'] ? 'q'.$offer['requirement'].' ' : '') . ($offer['attribute'] ?? ''))) ?: 'Standaard' ?></span><span><?= price($offer) ?></span><span><?= h($offer['player']) ?></span></div>
    <?php endforeach; ?>
  </div>
</section>

<footer>V2 is voorlopig een aparte shell. De bestaande website blijft leidend tot de migratie.</footer>
</main>
</div>
</body>
</html>
