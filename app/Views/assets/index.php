<?php declare(strict_types=1); $s=$summary??[]; ?>
<section class="page-intro"><div><span class="kicker">GUILD WARS ASSETS</span><h1>Inventory icons</h1><p>LittyWatch gebruikt de lokale <code>item_icon_12345.png</code>-bestanden voor Dashboard, Items en itemdetails.</p></div><div class="actions"><a class="btn" href="/admin/assets-scan">Icons opnieuw indexeren</a><a class="btn secondary" href="/admin">← Admin</a></div></section>
<div class="metric-grid">
  <article class="metric"><span>Bestanden gevonden</span><strong><?=(int)$count?></strong></article>
  <article class="metric"><span>Geïndexeerd</span><strong><?=(int)($s['assets']??0)?></strong></article>
  <article class="metric"><span>Gekoppeld</span><strong><?=(int)($s['linked']??0)?></strong></article>
  <article class="metric"><span>Ongekoppeld</span><strong><?=(int)($s['unlinked']??0)?></strong></article>
</div>
<section class="surface"><div class="section-heading"><div><span class="kicker">LOKALE BRON</span><h2>Assetmap</h2></div></div><code><?=h($directory)?></code><p class="muted">Bestanden mogen direct in deze map of in submappen staan. De indexer herkent <code>item_icon_12345.png</code>, <code>itemIcon_12345.png</code> en <code>item-icon-12345.png</code>. Bestaande itemkoppelingen blijven behouden.</p></section>
