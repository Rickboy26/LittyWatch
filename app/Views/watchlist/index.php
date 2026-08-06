<?php declare(strict_types=1);
$format=static fn(mixed $v):string=>$v===null?'—':number_format((float)$v,2,',','.').'e';
$state=static function(?float $current,?float $target,string $direction):string{if($current===null||$target===null||$current<=0)return'';$hit=$direction==='below'?$current<=$target:$current>=$target;return $hit?'badge buy':'badge trade';};
?>
<section class="page-intro"><div><span class="kicker">PERSOONLIJK</span><h1>Watchlist & koersdoelen</h1><p>Volg marktvarianten en laat LittyWatch automatisch een alert maken zodra jouw koop- of verkoopdoel wordt geraakt.</p></div><div class="actions"><a href="/alerts">Bekijk alerts</a></div></section>
<?php if($message):?><div class="surface" style="border-color:rgba(81,199,141,.45)"><?=h($message)?></div><?php endif;?>
<?php if($error):?><div class="surface" style="border-color:rgba(239,106,103,.55)"><?=h($error)?></div><?php endif;?>
<section class="surface"><div class="section-heading"><div><span class="kicker">TOEVOEGEN</span><h2>Markt volgen</h2></div></div>
<form method="post" action="/watchlist" class="formgrid">
<label class="wide">Marktvariant<input name="market_key" list="watchlist-markets" required placeholder="Zoek of plak een market_key"></label>
<datalist id="watchlist-markets"><?php foreach($options as $option):?><option value="<?=h($option['market_key'])?>"><?=h($option['item'])?> · WTB <?=h($format($option['best_wtb_ecto']))?> · WTS <?=h($format($option['best_wts_ecto']))?></option><?php endforeach;?></datalist>
<label>Eigen label<input name="label" placeholder="Optioneel"></label><label>Koopdoel<input name="target_buy_ecto" inputmode="decimal" placeholder="Maximaal ecto"></label><label>Verkoopdoel<input name="target_sell_ecto" inputmode="decimal" placeholder="Minimaal ecto"></label><div style="align-self:end"><button class="btn" type="submit">Opslaan</button></div>
</form></section>
<section class="surface"><div class="section-heading"><div><span class="kicker">OVERZICHT</span><h2>Mijn watchlist</h2></div><span class="muted"><?=count($rows)?> gevolgd</span></div>
<div class="tablewrap"><table><thead><tr><th>Item</th><th>Hoogste WTB</th><th>Laagste WTS</th><th>Koopdoel</th><th>Verkoopdoel</th><th></th></tr></thead><tbody>
<?php if($rows===[]):?><tr><td colspan="6" class="muted">Nog niets op je watchlist.</td></tr><?php endif;?>
<?php foreach($rows as $row):$wtb=$row['best_wtb_ecto']!==null?(float)$row['best_wtb_ecto']:null;$wts=$row['best_wts_ecto']!==null?(float)$row['best_wts_ecto']:null;$buy=$row['target_buy_ecto']!==null?(float)$row['target_buy_ecto']:null;$sell=$row['target_sell_ecto']!==null?(float)$row['target_sell_ecto']:null;?>
<tr><td><a class="itemlink" href="/market?key=<?=rawurlencode((string)$row['market_key'])?>"><?=h($row['label'])?></a><small class="muted" style="display:block"><?=h($row['market_key'])?></small></td><td><?=h($format($wtb))?></td><td><?=h($format($wts))?></td><td><span class="<?=$state($wts,$buy,'below')?>"><?=h($format($buy))?></span></td><td><span class="<?=$state($wtb,$sell,'above')?>"><?=h($format($sell))?></span></td><td><form method="post" action="/watchlist"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button class="btn secondary" type="submit">Verwijder</button></form></td></tr>
<?php endforeach;?></tbody></table></div></section>
