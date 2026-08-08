<?php
declare(strict_types=1);
$s=$summary??[];
$rows=$assets??[];
$itemOptions=$items??[];
$autoItems=$autoItems??[];
$q=(string)($q??'');
$filter=(string)($filter??'all');
$page=(int)($page??1);
$limit=(int)($limit??72);
$indexed=(int)($s['assets']??0);
$files=(int)($s['files']??0);
$marketItems=(int)($s['market_items']??0);
$linkedItems=(int)($s['linked_items']??0);
$unlinkedItems=(int)($s['unlinked_items']??max(0,$marketItems-$linkedItems));
$usedAssets=(int)($s['linked']??0);
?>
<section class="page-intro asset-page-intro">
  <div>
    <span class="kicker">GUILD WARS · GW.DAT</span>
    <h1>Inventory icons</h1>
    <p>Alle echte 64×64 inventory icons staan lokaal in LittyWatch. Koppel marktitems aan een DAT-icoon automatisch of corrigeer uitzonderingen handmatig.</p>
  </div>
  <div class="actions"><a class="btn" href="/admin/assets-scan"><?= $indexed>0?'Icons opnieuw indexeren':'Inventory icons indexeren' ?></a><a class="btn secondary" href="/admin">← Admin</a></div>
</section>

<?php if($message):?><div class="notice success"><?=h($message)?></div><?php endif;?>
<?php if($error):?><div class="notice error"><?=h($error)?></div><?php endif;?>

<div class="metric-grid asset-metrics">
  <article class="metric"><span>Inventory bestanden</span><strong><?=$files?></strong><small>uit jouw Gw.dat-pakket</small></article>
  <article class="metric"><span>Geïndexeerd</span><strong><?=$indexed?></strong><small>DAT-ID's beschikbaar</small></article>
  <article class="metric"><span>Marketitems met icoon</span><strong><?=$linkedItems?></strong><small><?=$usedAssets?> iconbestanden gebruikt</small></article>
  <article class="metric"><span>Marketitems zonder icoon</span><strong><?=$unlinkedItems?></strong><small>van <?=$marketItems?> bekende marktitems</small></article>
</div>

<?php if($files>0 && $indexed===0):?>
<section class="surface asset-index-callout">
  <div><span class="kicker">EERSTE KEER</span><h2>Icons staan al in de website</h2><p>De bestanden zijn klaar. Indexeer ze één keer zodat LittyWatch de DAT file IDs kan bewaren en daarna automatisch kan herkennen.</p></div>
  <a class="btn" href="/admin/assets-scan">Nu indexeren</a>
</section>
<?php else:?>

<?php if($indexed>0):?>
<?php endif;?>


<section class="surface asset-auto-map">
 <div class="asset-auto-head"><div>
  <span class="kicker">PLAYER INVENTORY ICONS · PHASE 3R</span>
  <h2>Inventory icons rechtstreeks aan itemnamen koppelen</h2>
  <p>Leest de publieke GW1-itemcatalogus en de bijbehorende benoemde inventory-assets rechtstreeks uit dezelfde bron. Itemnaam en plaatje horen daar al bij elkaar. LittyWatch slaat het plaatje éénmalig lokaal op en gebruikt daarna alleen de lokale kopie.</p>
 </div><div class="asset-auto-actions">
  <button class="btn" type="button" data-direct-assets>Ontbrekende icons opnieuw proberen</button>
  <button class="btn secondary" type="button" data-show-missing>Ontbrekende items tonen</button>
  <button class="btn secondary" type="button" data-strict-catalog>Niet-bestaande marktitems opschonen</button>
 </div></div>
 <div class="statgrid asset-coverage" data-asset-coverage>
  <div class="stat"><span>Catalogus</span><strong data-cov-total>…</strong></div>
  <div class="stat"><span>Lokaal icoon</span><strong data-cov-linked>…</strong></div>
  <div class="stat"><span>Ontbrekend</span><strong data-cov-missing>…</strong></div>
 </div>
 <p class="muted" data-direct-assets-status>De spelerswebsite gebruikt de lokale named inventory-icons als primaire bron.</p>
 <div data-missing-wrap hidden>
  <h3>Ontbrekende inventory icons</h3>
  <input class="input" type="search" placeholder="Zoek ontbrekend item…" data-missing-search>
  <div class="asset-missing-list" data-missing-list></div>
 </div>
</section>
<script src="/assets/js/gwmarket-direct-assets.js?v=3q" defer></script>

<section class="surface asset-browser-tools">
  <div class="asset-browser-copy">
    <span class="kicker">HANDMATIGE REVIEW</span>
    <h2>DAT icon browser</h2>
    <p>De 5277 bestanden zijn alle beschikbare game-icons; ze hoeven dus niet allemaal aan een marktitem gekoppeld te worden. Gebruik dit overzicht alleen voor uitzonderingen of correcties.</p>
  </div>
  <form method="get" action="/game-assets" class="asset-searchbar">
    <input name="q" value="<?=h($q)?>" placeholder="Zoek DAT file ID, bestandsnaam of gekoppeld item">
    <select name="filter">
      <option value="all" <?=$filter==='all'?'selected':''?>>Alle icons</option>
      <option value="unlinked" <?=$filter==='unlinked'?'selected':''?>>Nog niet gebruikt</option>
      <option value="linked" <?=$filter==='linked'?'selected':''?>>Gebruikt door item</option>
    </select>
    <button class="btn">Zoeken</button>
    <?php if($q!==''||$filter!=='all'):?><a class="btn secondary" href="/game-assets">Reset</a><?php endif;?>
  </form>
</section>

<datalist id="market-item-options">
<?php foreach($itemOptions as$item):?>
  <option value="<?=h((string)($item['item']??''))?>"><?=h((string)($item['item_key']??''))?></option>
<?php endforeach;?>
</datalist>

<section class="asset-icon-grid">
<?php if(!$rows):?>
  <div class="surface dashboard-empty">Geen inventory icons gevonden met deze filters.</div>
<?php endif;?>
<?php foreach($rows as$a):
  $id=(int)($a['id']??0);
  $dat=(int)($a['dat_file_id']??0);
  $web=(string)($a['web_path']??'');
  $links=trim((string)($a['link_names']??$a['linked_item_name']??''));
  $linkCount=(int)($a['link_count']??($links!==''?1:0));
?>
  <article class="asset-icon-card <?=$linkCount>0?'linked':''?>">
    <div class="asset-icon-preview"><img src="<?=h($web)?>" alt="" loading="lazy"></div>
    <div class="asset-icon-meta"><strong>DAT <?=$dat?></strong><small><?=h((string)($a['source_filename']??''))?></small></div>
    <?php if($linkCount>0):?>
      <div class="asset-linked-name"><span><?=$linkCount===1?'Gekoppeld aan':$linkCount.' items gebruiken dit icoon'?></span><strong><?=h($links)?></strong></div>
      <form method="post" action="/game-assets" class="asset-link-form">
        <input type="hidden" name="action" value="unlink"><input type="hidden" name="asset_id" value="<?=$id?>">
        <input type="hidden" name="q" value="<?=h($q)?>"><input type="hidden" name="filter" value="<?=h($filter)?>"><input type="hidden" name="page" value="<?=$page?>">
        <button class="asset-unlink" type="submit">Alle koppelingen van icoon wissen</button>
      </form>
    <?php else:?>
      <form method="post" action="/game-assets" class="asset-link-form">
        <input type="hidden" name="action" value="link"><input type="hidden" name="asset_id" value="<?=$id?>">
        <input type="hidden" name="q" value="<?=h($q)?>"><input type="hidden" name="filter" value="<?=h($filter)?>"><input type="hidden" name="page" value="<?=$page?>">
        <input name="item" list="market-item-options" placeholder="Itemnaam…" autocomplete="off" required>
        <button type="submit">Koppel</button>
      </form>
    <?php endif;?>
  </article>
<?php endforeach;?>
</section>

<?php if($rows):?>
<nav class="asset-pagination" aria-label="Icon pagina's">
  <?php if($page>1):?><a class="btn secondary" href="/game-assets?q=<?=rawurlencode($q)?>&filter=<?=rawurlencode($filter)?>&page=<?=$page-1?>">← Vorige</a><?php endif;?>
  <span>Pagina <?=$page?></span>
  <?php if(count($rows)>=$limit):?><a class="btn secondary" href="/game-assets?q=<?=rawurlencode($q)?>&filter=<?=rawurlencode($filter)?>&page=<?=$page+1?>">Volgende →</a><?php endif;?>
</nav>
<?php endif;?>
<?php endif;?>



<script src="/assets/js/named-asset-admin.js?v=3r" defer></script>

<script>
document.querySelector('[data-strict-catalog]')?.addEventListener('click',async function(){
 const status=document.querySelector('[data-direct-assets-status]');
 this.disabled=true;
 if(status)status.textContent='Bestaande marktdata tegen de echte itemcatalogus controleren…';
 try{
  const r=await fetch('/admin/strict-catalog-enforce',{method:'POST',headers:{Accept:'application/json'}});
  const d=await r.json();
  if(!r.ok||!d.ok)throw new Error(d.error||`HTTP ${r.status}`);
  if(status)status.textContent=`Strict Catalog klaar · ${d.checked} actieve aanbiedingen gecontroleerd · ${d.quarantined} niet-bestaande/generieke aanbiedingen uit de spelersmarkt gehaald · ${d.normalized} namen naar catalogusvorm hersteld.`;
 }catch(e){if(status)status.textContent='Fout: '+(e?.message||e);}
 finally{this.disabled=false;}
});
</script>
