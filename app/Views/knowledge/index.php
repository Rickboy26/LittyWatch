<?php declare(strict_types=1); ?>
<section class="pagehead"><div><div class="eyebrow">GUILD WARS KNOWLEDGE BASE</div><h1>Itemprofielen & attributes</h1><p class="muted">LittyWatch gebruikt per itemtype alleen de eigenschappen die werkelijk de marktvariant bepalen.</p></div><div class="actions"><a class="btn" href="/knowledge-pack">Wiki Knowledge Pack</a><a href="/admin/knowledge-seed">Knowledge Base opnieuw seeden</a><a class="btn secondary" href="/admin/parser-lab">Parser Lab</a></div></section>
<div class="statgrid">
  <div class="stat"><span>Items</span><b><?=h((string)$stats['items'])?></b></div>
  <div class="stat"><span>Aliassen</span><b><?=h((string)$stats['aliases'])?></b></div>
  <div class="stat"><span>Attributes</span><b><?=h((string)$stats['attributes'])?></b></div>
  <div class="stat"><span>Profielen</span><b><?=h((string)$stats['profiles'])?></b></div>
  <div class="stat"><span>Toegewezen items</span><b><?=h((string)$stats['profile_assignments'])?></b></div>
</div>
<section class="panel"><h2>Marktprofielen</h2><div class="profilegrid">
<?php foreach($profiles as $profile): ?><article class="profilecard"><h3><?=h($profile['name'])?></h3><p class="muted"><?=h($profile['description'])?></p><p><strong>Volgen:</strong> <?=h(implode(', ',$profile['track']))?></p><p><strong>Negeren:</strong> <?=h($profile['ignore']?implode(', ',$profile['ignore']):'niets')?></p><p><strong>Marktsleutel:</strong> <code><?=h(implode(' + ',$profile['market_key']))?></code></p></article><?php endforeach; ?>
</div></section>
<div class="twocol"><section class="panel"><h2>Itemtoewijzingen</h2><div class="tablewrap"><table><thead><tr><th>Item</th><th>Categorie</th><th>Profiel</th></tr></thead><tbody><?php foreach($assignments as $row): ?><tr><td><?=h($row['item_name'])?></td><td><?=h($row['category_key'])?></td><td><?=h($row['profile_name'])?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel"><h2>Attributes</h2><div class="tablewrap"><table><thead><tr><th>Attribute</th><th>Profession</th><th>Aliassen</th></tr></thead><tbody><?php foreach($attributes as $attribute): ?><tr><td><?=h($attribute['name'])?></td><td><?=h((string)$attribute['profession'])?></td><td><code><?=h(implode(', ',$attribute['aliases']))?></code></td></tr><?php endforeach; ?></tbody></table></div></section></div>
<style>.profilegrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px}.profilecard{background:#0b1527;border:1px solid var(--line);border-radius:12px;padding:15px}.profilecard h3{margin-top:0}.profilecard p{margin:.55em 0}</style>
