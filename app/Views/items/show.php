<?php declare(strict_types=1); ?>
<div class="pagehead">
  <div><div class="eyebrow">ITEMOVERZICHT</div><h1><?= h((string)$item['item']) ?></h1><p class="muted">Prijsindicaties zijn gebaseerd op advertenties, niet op bevestigde transacties.</p></div>
  <a class="btn secondary" href="items">← Alle items</a>
</div>

<div class="statgrid">
  <div class="stat"><span>Aanbiedingen</span><b><?= (int)$item['offers'] ?></b></div>
  <div class="stat"><span>WTB</span><b><?= (int)$item['buy_count'] ?></b></div>
  <div class="stat"><span>WTS</span><b><?= (int)$item['sell_count'] ?></b></div>
  <div class="stat"><span>Beste WTB</span><b><?= $item['highest_buy']!==null?number_format((float)$item['highest_buy'],2,',','.').'e':'—' ?></b></div>
  <div class="stat"><span>Laagste WTS</span><b><?= $item['lowest_sell']!==null?number_format((float)$item['lowest_sell'],2,',','.').'e':'—' ?></b></div>
  <div class="stat"><span>Potentiële spread</span><b><?php if($item['highest_buy']!==null&&$item['lowest_sell']!==null): ?><?= number_format((float)$item['highest_buy']-(float)$item['lowest_sell'],2,',','.') ?>e<?php else: ?>—<?php endif; ?></b></div>
</div>

<div class="twocol">
<section class="panel"><h2>Varianten</h2><div class="tablewrap"><table><thead><tr><th>Variant</th><th>Offers</th><th>WTB</th><th>WTS</th><th>Gem. WTB</th><th>Gem. WTS</th></tr></thead><tbody>
<?php foreach($variants as $variant): ?><tr><td><?= h((string)$variant['variant']) ?></td><td><?= (int)$variant['offers'] ?></td><td><?= (int)$variant['buy_count'] ?></td><td><?= (int)$variant['sell_count'] ?></td><td><?= $variant['avg_buy']!==null?number_format((float)$variant['avg_buy'],2,',','.').'e':'—' ?></td><td><?= $variant['avg_sell']!==null?number_format((float)$variant['avg_sell'],2,',','.').'e':'—' ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="panel"><h2>Snelle beoordeling</h2><div class="callout"><strong>Vraagzijde</strong><p><?= (int)$item['buy_count'] ?> koopadvertenties geregistreerd.</p></div><div class="callout"><strong>Aanbodzijde</strong><p><?= (int)$item['sell_count'] ?> verkoopadvertenties geregistreerd.</p></div><div class="callout"><strong>Datakwaliteit</strong><p><?= (int)$item['review_count'] ?> aanbiedingen staan nog ter controle.</p></div></section>
</div>

<section class="panel"><h2>Recente aanbiedingen</h2><div class="tablewrap"><table><thead><tr><th>Type</th><th>Variant</th><th>Prijs</th><th>Speler</th><th>Advertentie</th></tr></thead><tbody>
<?php foreach($offers as $offer): ?><tr><td><span class="badge <?= h((string)$offer['trade_type']) ?>"><?= strtoupper(h((string)$offer['trade_type'])) ?></span></td><td><?= h((string)($offer['details'] ?: 'Standaard')) ?><div class="muted"><?= (int)round((float)$offer['confidence']*100) ?>% · <?= h((string)$offer['quality_status']) ?></div></td><td><?= $offer['price_amount']!==null?h((string)$offer['price_amount']).h((string)$offer['price_currency']):'—' ?><?php if($offer['unit_price_ecto']!==null): ?><div class="muted"><?= number_format((float)$offer['unit_price_ecto'],2,',','.') ?>e/stuk</div><?php endif; ?></td><td><?= h((string)$offer['player']) ?><div class="muted"><?= h((string)$offer['posted_at']) ?></div></td><td><code><?= h((string)($offer['raw_segment'] ?: $offer['message'])) ?></code></td></tr><?php endforeach; ?>
</tbody></table></div></section>
