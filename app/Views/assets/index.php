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

<?php if($indexed>0 && $unlinkedItems>0):?>
<section class="surface asset-auto-map" data-inventory-auto-map>
  <div class="asset-auto-head">
    <div>
      <span class="kicker">AUTOMATISCHE HERKENNING</span>
      <h2>Koppel de juiste inventory icons</h2>
      <p>LittyWatch gebruikt de Guild Wars Wiki alleen als tijdelijke herkenningsbron. De Wiki-icon wordt via LittyWatch zelf ingelezen zodat de browser de pixels betrouwbaar kan vergelijken met jouw lokale Gw.dat-icons. Alleen sterke matches worden opgeslagen; de spelerswebsite blijft daarna uitsluitend het lokale inventory icon gebruiken.</p>
    </div>
    <div class="asset-auto-actions">
      <button class="btn" type="button" data-auto-map-start>Automatisch herkennen</button>
      <button class="btn secondary" type="button" data-auto-map-stop hidden>Stoppen</button>
    </div>
  </div>

  <div class="asset-auto-progress" aria-hidden="true"><span data-auto-map-progress></span></div>
  <div class="asset-auto-statusline">
    <strong data-auto-map-status>Klaar om <?=$unlinkedItems?> marktitems te controleren.</strong>
    <span data-auto-map-processed>0/<?=count($autoItems)?></span>
  </div>
  <p class="muted asset-auto-detail" data-auto-map-detail>Twijfelgevallen worden bewust niet automatisch gekoppeld en blijven beschikbaar voor handmatige review.</p>

  <div class="asset-auto-results">
    <div><span>Nieuwe matches</span><strong data-auto-map-matched>0</strong></div>
    <div><span>Niet zeker genoeg</span><strong data-auto-map-unresolved>0</strong></div>
    <div><span>Fouten</span><strong data-auto-map-failed>0</strong></div>
  </div>
</section>
<?php elseif($indexed>0 && $marketItems>0):?>
<section class="surface asset-auto-map asset-auto-complete">
  <div><span class="kicker">INVENTORY ICONS</span><h2>Alle bekende marktitems hebben een icoon</h2><p>Nieuwe items die later door de parser worden ontdekt verschijnen hier vanzelf opnieuw als te koppelen item.</p></div>
</section>
<?php endif;?>

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

<script type="application/json" id="inventory-auto-items"><?=json_encode($autoItems,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?:'[]'?></script>
<script src="/assets/js/inventory-icon-matcher.js?v=3m7" defer></script>
