<?php declare(strict_types=1); ?>
<div class="pagehead">
  <div><div class="eyebrow">MARKTPLAATS</div><h1>Items</h1><p class="muted">Alle herkende Guild Wars-items met recente handelsactiviteit.</p></div>
  <form class="searchbar" method="get" action="items"><input name="q" value="<?= h($query) ?>" placeholder="Zoek bijvoorbeeld BDS, Eternal Blade of GotT"><button class="btn">Zoeken</button></form>
</div>

<section class="panel">
  <div class="tablewrap"><table class="market-table"><thead><tr><th>Item</th><th>Aanbiedingen</th><th>WTB</th><th>WTS</th><th>Gem. WTB</th><th>Gem. WTS</th><th>Laatste activiteit</th></tr></thead><tbody>
  <?php if (!$items): ?><tr><td colspan="7" class="muted">Geen items gevonden.</td></tr><?php endif; ?>
  <?php foreach ($items as $row): ?>
    <tr>
      <td><a class="itemlink" href="item?name=<?= rawurlencode((string)$row['item']) ?>"><?= h((string)$row['item']) ?></a></td>
      <td><?= (int)$row['offers'] ?></td><td><?= (int)$row['buy_count'] ?></td><td><?= (int)$row['sell_count'] ?></td>
      <td><?= $row['avg_buy']!==null ? number_format((float)$row['avg_buy'],2,',','.').'e' : '—' ?></td>
      <td><?= $row['avg_sell']!==null ? number_format((float)$row['avg_sell'],2,',','.').'e' : '—' ?></td>
      <td class="muted"><?= h((string)($row['latest_posted_at'] ?? '—')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
</section>
