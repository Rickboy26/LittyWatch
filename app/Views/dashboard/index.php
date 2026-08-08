<?php declare(strict_types=1); $title='Dashboard · LittyWatch';
$fmt=static function(float $v): string{return number_format($v,abs($v-round($v))>.001?2:0,',','.');};
$imageMap=['Platinum ↔ Ecto'=>'Glob of Ectoplasm','Ecto ↔ Armbrace'=>'Armbrace of Truth','Ecto ↔ Zaishen Key'=>'Zaishen Key','Ecto ↔ Obsidian Shard'=>'Obsidian Shard'];
?>
<section class="page-intro dashboard-intro"><div><span class="kicker">KAMADAN MARKET INTELLIGENCE</span><h1>Guild Wars handel, helder in beeld.</h1><p>De belangrijkste koersen, bewegingen en nieuwste aanbiedingen op één plek.</p></div></section>

<section class="dashboard-market-strip">
  <div class="exchange-compact">
    <div class="compact-head"><div><span class="kicker">LIVE MARKTDATA</span><h2>Exchange rates</h2></div><small><?=h((string)($exchangeRates['source']??''))?></small></div>
    <div class="exchange-mini-grid">
      <?php foreach(($exchangeRates['rates']??[]) as $rate): $img=$imageMap[$rate['label']]??$rate['right_unit']; ?>
      <article class="exchange-mini-card">
        <img src="/item-image.php?item=<?=rawurlencode((string)$img)?>&size=48" alt="">
        <div><span><?=h((string)$rate['label'])?></span><strong><?=$fmt((float)$rate['left_amount'])?> <?=h((string)$rate['left_unit'])?> <em>=</em> <?=$fmt((float)$rate['right_amount'])?> <?=h((string)$rate['right_unit'])?></strong></div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="mover-grid">
    <?php foreach(['gainer'=>['Hardste stijger','▲'],'loser'=>['Hardste daler','▼']] as$key=>$meta): $m=$movers[$key]??null; ?>
    <article class="mover-card <?=$key?>">
      <span class="mover-label"><?=$meta[1]?> <?=h($meta[0])?></span>
      <?php if($m): ?>
        <img src="/item-image.php?item=<?=rawurlencode((string)$m['item'])?>&size=72" alt="">
        <a href="/item?name=<?=rawurlencode((string)$m['item'])?>"><?=h((string)$m['item'])?></a>
        <strong><?=($m['percent']>=0?'+':'').number_format((float)$m['percent'],1,',','.')?>%</strong>
        <small>Nu ≈ <?=$fmt((float)$m['current'])?>e</small>
      <?php else: ?>
        <div class="mover-empty">Nog onvoldoende prijsdata uit twee opeenvolgende 24-uursperiodes.</div>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="surface dashboard-offers">
  <div class="section-heading"><div><span class="kicker">KAMADAN</span><h2>Nieuwste aanbiedingen</h2></div><div class="actions"><a class="btn secondary" href="/items">Bekijk alle items</a></div></div>
  <div class="tablewrap"><table><thead><tr><th>Offer</th><th>Item</th><th>Prijs</th><th>Gemiddelde prijs</th><th>Speler</th><th>Datum</th></tr></thead><tbody>
  <?php foreach(array_slice($offers,0,20) as$o): ?>
    <tr>
      <td><span class="badge <?=h((string)$o['trade_type'])?>"><?=strtoupper(h((string)$o['trade_type']))?></span></td>
      <td><div class="item-cell"><img class="item-thumb" src="/item-image.php?item=<?=rawurlencode((string)$o['item'])?>&size=48" alt=""><div><a class="itemlink" href="/item?name=<?=rawurlencode((string)$o['item'])?>"><?=h((string)$o['item'])?></a><?php if(!empty($o['details'])&&$o['details']!=='Standaard'):?><div class="muted"><?=h((string)$o['details'])?></div><?php endif;?></div></div></td>
      <td><div class="price-pair"><?php if(($o['price_basis']??'')==='barter'&&!empty($o['exchange_item'])): ?><strong><?=h((string)($o['exchange_give_quantity']??1))?> : <?=h((string)($o['exchange_receive_quantity']??1))?></strong><small>voor <?=h((string)$o['exchange_item'])?></small><?php else: ?><strong><?=$o['price_amount']!==null?h((string)$o['price_amount'].($o['price_currency']??'')):'—'?></strong><?php if($o['unit_price_ecto']!==null):?><small><?=number_format((float)$o['unit_price_ecto'],2,',','.')?>e/stuk</small><?php endif;?><?php endif;?></div></td>
      <td><?php if($o['average_price_ecto']!==null):?><strong>≈ <?=number_format((float)$o['average_price_ecto'],2,',','.')?>e</strong><?php else:?><span class="muted">—</span><?php endif;?></td>
      <td><?=h((string)$o['player'])?></td>
      <td class="nowrap"><?=h(lw_local_datetime((string)$o['posted_at']))?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</section>
