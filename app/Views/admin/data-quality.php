<?php declare(strict_types=1);
$labels=[
 'all'=>'Alle kwaliteitsgevallen',
 'unpriced'=>'Geen bruikbare geldprijs',
 'uncertain'=>'Onzekere prijs / unitprijs',
 'outlier'=>'Markt-outliers',
 'no_catalog_item'=>'Geen catalogusitem',
 'low_confidence'=>'Lage parserconfidence',
 'parser_review'=>'Alle parser review',
];
$fmtRaw=static function(array $row):string{
 if($row['price_amount']===null)return '—';
 return (string)$row['price_amount'].(string)$row['price_currency'];
};
?>
<section class="page-intro"><div><span class="kicker">DATA QUALITY WORKBENCH</span><h1>Werk de zwakke plekken gericht weg.</h1><p>Bekijk concrete advertenties per kwaliteitsgroep. Filters veranderen alleen de analyseweergave; marktdata wordt hier niet automatisch aangepast.</p></div><div class="actions"><a class="btn secondary" href="/admin">← Beheer</a><a class="btn secondary" href="/parser-review">Parser Review</a></div></section>

<section class="surface">
 <div class="section-heading"><div><span class="kicker">GROEPEN</span><h2>Probleemcategorieën</h2></div></div>
 <div class="quality-tabs">
 <?php foreach(($overview['issues']??[]) as $issue): ?>
   <a class="btn secondary <?=($category??'all')===$issue['issue_key']?'active':''?>" href="/admin/data-quality?category=<?=rawurlencode((string)$issue['issue_key'])?>">
     <?=h((string)$issue['label'])?> · <?=(int)$issue['total']?>
   </a>
 <?php endforeach; ?>
 </div>
</section>

<section class="surface">
 <form class="filters" method="get" action="/admin/data-quality">
  <select name="category">
   <?php foreach($labels as $key=>$label): ?><option value="<?=h($key)?>" <?=$category===$key?'selected':''?>><?=h($label)?></option><?php endforeach; ?>
  </select>
  <select name="type">
   <option value="" <?=$type===''?'selected':''?>>Alle types</option>
   <option value="buy" <?=$type==='buy'?'selected':''?>>WTB</option>
   <option value="sell" <?=$type==='sell'?'selected':''?>>WTS</option>
   <option value="trade" <?=$type==='trade'?'selected':''?>>WTT</option>
  </select>
  <input type="search" name="q" value="<?=h($query)?>" placeholder="Zoek item, speler, segment of reden">
  <select name="limit"><?php foreach([50,100,200,500] as $n): ?><option value="<?=$n?>" <?=$limit===$n?'selected':''?>><?=$n?> regels</option><?php endforeach; ?></select>
  <button class="btn">Filter</button>
 </form>
 <p class="muted"><strong><?=count($cases)?></strong> resultaten · <?=h($labels[$category]??$labels['all'])?></p>
</section>

<section class="surface">
 <div class="tablewrap"><table>
  <thead><tr><th>Type</th><th>Item / status</th><th>Prijs</th><th>Speler</th><th>Advertentie</th><th>Reden</th></tr></thead>
  <tbody>
  <?php foreach($cases as $row):
    $priceStatus=(string)($row['price_quality_status']??'trusted');
    $reason=$priceStatus!=='trusted' ? (string)($row['price_quality_reason']?:$priceStatus) : (string)($row['quality_reason']??'');
  ?>
   <tr>
    <td><span class="badge <?=h((string)$row['trade_type'])?>"><?=strtoupper(h((string)$row['trade_type']))?></span></td>
    <td>
      <?php if((string)$row['item']!==''): ?><a class="itemlink" href="/item?name=<?=rawurlencode((string)$row['item'])?>"><?=h((string)$row['item'])?></a><?php else: ?><strong>—</strong><?php endif; ?>
      <div class="muted"><?=h((string)$row['quality_status'])?> · <?=number_format((float)$row['confidence']*100,0)?>%</div>
    </td>
    <td>
      <strong><?=h($fmtRaw($row))?></strong>
      <?php if($row['unit_price_ecto']!==null): ?><div><?=number_format((float)$row['unit_price_ecto'],3,',','.')?>e/stuk</div><?php endif; ?>
      <?php if($row['price_baseline_ecto']!==null): ?><div class="muted">baseline <?=number_format((float)$row['price_baseline_ecto'],3,',','.')?>e</div><?php endif; ?>
    </td>
    <td><?=h((string)$row['player'])?><div class="muted"><?=h(lw_local_datetime((string)$row['posted_at']))?></div></td>
    <td><code><?=h((string)($row['raw_segment']?:$row['message']))?></code></td>
    <td>
      <?=h($reason!==''?$reason:'—')?>
      <?php if((string)$row['quality_status']==='review'): ?><div><a href="/parser-review?q=<?=rawurlencode((string)($row['raw_segment']?:$row['item']))?>">Open in Parser Review →</a></div><?php endif; ?>
    </td>
   </tr>
  <?php endforeach; ?>
  <?php if(!$cases): ?><tr><td colspan="6"><p class="muted">Geen resultaten voor dit filter.</p></td></tr><?php endif; ?>
  </tbody>
 </table></div>
</section>
