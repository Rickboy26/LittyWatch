<?php declare(strict_types=1); $title='LittyWatch'; ?>
<div class="top"><div><h1 style="margin:0">LittyWatch</h1><div class="muted">v0.8 — nieuwe applicatiefundering, bestaande marktdata behouden</div></div><div class="actions"><a href="collect.php">Nu ophalen</a> <a href="reparse.php">Opnieuw parseren</a> <a href="parser-v2-test.php">Parser v2 Lab</a></div></div>
<div class="cards">
<?php foreach (['messages'=>'Berichten','offers'=>'Aanbiedingen','accepted'=>'Geaccepteerd','buy'=>'WTB','sell'=>'WTS','review'=>'Te controleren'] as $key=>$label): ?>
<div class="card"><span class="muted"><?= h($label) ?></span><b><?= (int)$counters[$key] ?></b></div>
<?php endforeach; ?>
</div>
<div class="grid">
<section class="panel"><h2>Flip-kansen</h2><p class="muted">Mediaan van recente unieke traders. Minimaal twee kopers en twee verkopers.</p><table><thead><tr><th>Item</th><th>WTS</th><th>WTB</th><th>Marge</th></tr></thead><tbody>
<?php if (!$flips): ?><tr><td colspan="4" class="muted">Nog onvoldoende vergelijkbare WTB/WTS-prijzen.</td></tr><?php endif; ?>
<?php foreach ($flips as $flip): ?><tr><td><?= h((string)$flip['item']) ?></td><td><?= number_format((float)$flip['sell_median'],2) ?>e</td><td><?= number_format((float)$flip['buy_median'],2) ?>e</td><td><?= number_format((float)$flip['spread'],2) ?>e</td></tr><?php endforeach; ?>
</tbody></table></section>
<section class="panel"><div class="top"><h2>Herkende aanbiedingen</h2><form class="filters"><input name="q" value="<?= h($query) ?>" placeholder="Zoek item, speler of tekst"><select name="type"><option value="">Alles</option><option value="buy" <?= $type==='buy'?'selected':'' ?>>WTB</option><option value="sell" <?= $type==='sell'?'selected':'' ?>>WTS</option></select><button class="btn">Zoeken</button></form></div><div class="scroll"><table><thead><tr><th>Type</th><th>Item</th><th>Prijs</th><th>Speler / origineel</th></tr></thead><tbody>
<?php foreach ($offers as $offer): ?><tr><td><span class="badge <?= h((string)$offer['trade_type']) ?>"><?= strtoupper(h((string)$offer['trade_type'])) ?></span></td><td><strong><?= h((string)$offer['item']) ?></strong><?php if (!empty($offer['details'])): ?><div class="muted"><?= h((string)$offer['details']) ?></div><?php endif; ?><small class="muted"><?= (int)round((float)$offer['confidence']*100) ?>% · <?= h((string)($offer['quality_status'] ?? 'review')) ?></small></td><td><?= $offer['price_amount']!==null?h((string)$offer['price_amount']).h((string)$offer['price_currency']):'-' ?><?php if ($offer['unit_price_ecto']!==null): ?><div class="muted"><?= number_format((float)$offer['unit_price_ecto'],2) ?>e/stuk</div><?php endif; ?></td><td><?= h((string)$offer['player']) ?><br><code><?= h((string)($offer['raw_segment'] ?: $offer['message'])) ?></code></td></tr><?php endforeach; ?>
</tbody></table></div></section>
</div>
