<?php declare(strict_types=1); ?>
<section class="page-intro"><div><span class="kicker">SYSTEEMBEHEER</span><h1>Beheer & hulpmiddelen</h1><p>Technische acties staan uit de hoofdinterface, zodat traders alleen zien wat ze nodig hebben.</p></div></section>
<div class="admin-grid">
  <section class="surface"><div class="section-heading"><div><span class="kicker">DATA</span><h2>Collectors & verwerking</h2></div></div><div class="tool-list">
    <a href="/admin/collect"><strong>Kamadan nu ophalen</strong><span>Haal de nieuwste handelsberichten op.</span></a>
    <a href="/admin/reparse"><strong>Marktindex volledig herbouwen</strong><span>Parse alle bronberichten opnieuw en bouw de canonieke Items-data uit structured offers.</span></a>
    <a href="/admin/market-maintenance"><strong>Market maintenance</strong><span>Werk lifecycle en actieve advertenties bij.</span></a>
    <a href="/admin/knowledge-seed"><strong>Knowledge Base seeden</strong><span>Installeer items, aliassen en profielen opnieuw.</span></a>
  </div></section>
  <section class="surface"><div class="section-heading"><div><span class="kicker">KWALITEIT</span><h2>Parser & review</h2></div></div><div class="tool-list">
    <a href="/parser-review"><strong>Parser Review</strong><span>Controleer twijfelachtige herkenningen.</span></a>
    <a href="/structured-offers"><strong>Structured Offers</strong><span>Vergelijk legacy met Parser v2.</span></a>
    <a href="/admin/parser-lab"><strong>Parser Lab</strong><span>Test losse Kamadan-berichten.</span></a>
    <a href="/knowledge"><strong>Knowledge Base</strong><span>Bekijk itemprofielen en attributes.</span></a>
  </div></section>
</div>


<?php $dq=$dataQuality['summary']??[]; ?>
<section class="surface">
  <div class="section-heading"><div><span class="kicker">DATA QUALITY</span><h2>Dataset gezondheid</h2><p>Actuele verdeling van parser- en prijsvertrouwen.</p></div><div class="actions"><a class="btn secondary" href="/admin/data-quality">Open workbench</a></div></div>
  <div class="metric-grid">
    <article class="metric"><span>Aanbiedingen</span><strong><?= (int)($dq['offers']??0) ?></strong></article>
    <article class="metric"><span>Betrouwbare prijzen</span><strong><?= (int)($dq['trusted_prices']??0) ?></strong></article>
    <article class="metric"><span>Zonder geldprijs</span><strong><?= (int)($dq['unpriced']??0) ?></strong></article>
    <article class="metric"><span>Parser review</span><strong><?= (int)($dq['parser_review']??0) ?></strong></article>
    <article class="metric"><span>Onzekere prijzen</span><strong><?= (int)($dq['uncertain_prices']??0) ?></strong></article>
    <article class="metric"><span>Outliers</span><strong><?= (int)($dq['outlier_prices']??0) ?></strong></article>
  </div>
</section>

<div class="twocol">
  <section class="surface">
    <div class="section-heading"><div><span class="kicker">PROBLEEMGROEPEN</span><h2>Meest voorkomende kwaliteitsproblemen</h2></div></div>
    <?php if(empty($dataQuality['issues'])): ?><p class="muted">Geen kwaliteitsproblemen gevonden.</p><?php else: ?>
      <div class="tablewrap"><table><thead><tr><th>Probleem</th><th>Aantal</th></tr></thead><tbody>
      <?php foreach($dataQuality['issues'] as $issue): ?><tr><td><a class="itemlink" href="/admin/data-quality?category=<?=rawurlencode((string)$issue['issue_key'])?>"><?=h((string)$issue['label'])?></a></td><td><strong><?=(int)$issue['total']?></strong></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
  <section class="surface">
    <div class="section-heading"><div><span class="kicker">MARKET TRUST</span><h2>Zwakste actieve markten</h2><p>Score combineert prijsdekking, onafhankelijke traders, samplegrootte en flags.</p></div></div>
    <?php if(empty($dataQuality['weak_markets'])): ?><p class="muted">Nog onvoldoende marktdata.</p><?php else: ?>
      <div class="tablewrap"><table><thead><tr><th>Item</th><th>Score</th><th>Coverage</th><th>Traders</th></tr></thead><tbody>
      <?php foreach(array_slice($dataQuality['weak_markets'],0,12) as $m): $t=$m['trust']; ?><tr>
        <td><a class="itemlink" href="/item?name=<?=rawurlencode((string)$m['item'])?>"><?=h((string)$m['item'])?></a></td>
        <td><strong><?=(int)$t['score']?>/100</strong><div class="muted"><?=h($t['label'])?></div></td>
        <td><?=(int)$t['coverage']?>%</td><td><?=(int)$t['traders']?></td>
      </tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
</div>

<section class="surface"><div class="section-heading"><div><span class="kicker">ITEMAFBEELDINGEN</span><h2>Guild Wars Wiki-cache</h2><p>Afbeeldingen worden bij het eerste gebruik automatisch lokaal gecachet.</p></div></div><div class="image-catalog">
<?php foreach($imageItems as $item=>$wiki): ?><article><img src="/item-image.php?item=<?=rawurlencode($item)?>&size=64" alt=""><div><strong><?=h($item)?></strong><small><?=h($wiki)?></small></div></article><?php endforeach; ?>
</div></section>
