<?php
declare(strict_types=1);
$s=$summary??[];
$rows=$assets??[];
$itemOptions=$items??[];
$q=(string)($q??'');
$filter=(string)($filter??'all');
$page=(int)($page??1);
$limit=(int)($limit??72);
$indexed=(int)($s['assets']??0);
$files=(int)($s['files']??0);
?>
<section class="page-intro asset-page-intro">
  <div>
    <span class="kicker">GUILD WARS · GW.DAT</span>
    <h1>Inventory icons</h1>
    <p>De echte 64×64 inventory icons uit jouw assetpakket. Zoek op DAT file ID en koppel alleen waar de extractor nog geen itemnaam kent.</p>
  </div>
  <div class="actions"><a class="btn" href="/admin/assets-scan"><?= $indexed>0?'Icons opnieuw indexeren':'5277 icons indexeren' ?></a><a class="btn secondary" href="/admin">← Admin</a></div>
</section>

<?php if($message):?><div class="notice success"><?=h($message)?></div><?php endif;?>
<?php if($error):?><div class="notice error"><?=h($error)?></div><?php endif;?>

<div class="metric-grid asset-metrics">
  <article class="metric"><span>Bestanden meegeleverd</span><strong><?=$files?></strong></article>
  <article class="metric"><span>Geïndexeerd</span><strong><?=$indexed?></strong></article>
  <article class="metric"><span>Gekoppeld</span><strong><?=(int)($s['linked']??0)?></strong></article>
  <article class="metric"><span>Nog te koppelen</span><strong><?=(int)($s['unlinked']??0)?></strong></article>
</div>

<?php if($files>0 && $indexed===0):?>
<section class="surface asset-index-callout">
  <div><span class="kicker">EERSTE KEER</span><h2>Icons staan al in de website</h2><p>De bestanden zijn klaar. Indexeer ze één keer zodat LittyWatch DAT file IDs kan bewaren en itemkoppelingen kan beheren.</p></div>
  <a class="btn" href="/admin/assets-scan">Nu indexeren</a>
</section>
<?php else:?>
<section class="surface asset-browser-tools">
  <form method="get" action="/game-assets" class="asset-searchbar">
    <input name="q" value="<?=h($q)?>" placeholder="Zoek DAT file ID, bestandsnaam of gekoppeld item">
    <select name="filter">
      <option value="all" <?=$filter==='all'?'selected':''?>>Alle icons</option>
      <option value="unlinked" <?=$filter==='unlinked'?'selected':''?>>Nog te koppelen</option>
      <option value="linked" <?=$filter==='linked'?'selected':''?>>Gekoppeld</option>
    </select>
    <button class="btn">Zoeken</button>
    <?php if($q!==''||$filter!=='all'):?><a class="btn secondary" href="/game-assets">Reset</a><?php endif;?>
  </form>
  <p class="muted">Tip: de extractor leverde bij deze batch alleen <code>itemIcon_12345.png</code> + file ID. LittyWatch verzint daarom geen itemnamen; zo voorkom je verkeerde plaatjes.</p>
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
  $linked=trim((string)($a['linked_item_name']??''));
?>
  <article class="asset-icon-card <?=$linked!==''?'linked':''?>">
    <div class="asset-icon-preview"><img src="<?=h($web)?>" alt="" loading="lazy"></div>
    <div class="asset-icon-meta"><strong>DAT <?=$dat?></strong><small><?=h((string)($a['source_filename']??''))?></small></div>
    <?php if($linked!==''):?>
      <div class="asset-linked-name"><span>Gekoppeld aan</span><strong><?=h($linked)?></strong></div>
      <form method="post" action="/game-assets" class="asset-link-form">
        <input type="hidden" name="action" value="unlink"><input type="hidden" name="asset_id" value="<?=$id?>">
        <input type="hidden" name="q" value="<?=h($q)?>"><input type="hidden" name="filter" value="<?=h($filter)?>"><input type="hidden" name="page" value="<?=$page?>">
        <button class="asset-unlink" type="submit">Koppeling wissen</button>
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
