<?php declare(strict_types=1); ?>
<section class="page-intro"><div><span class="kicker">SYSTEEMBEHEER</span><h1>Beheer & hulpmiddelen</h1><p>Technische acties staan uit de hoofdinterface, zodat traders alleen zien wat ze nodig hebben.</p></div></section>
<div class="admin-grid">
  <section class="surface"><div class="section-heading"><div><span class="kicker">DATA</span><h2>Collectors & verwerking</h2></div></div><div class="tool-list">
    <a href="/admin/collect"><strong>Kamadan nu ophalen</strong><span>Haal de nieuwste handelsberichten op.</span></a>
    <a href="/admin/reparse"><strong>Parser v2 opnieuw draaien</strong><span>Bouw structured offers opnieuw op.</span></a>
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
<section class="surface"><div class="section-heading"><div><span class="kicker">ITEMAFBEELDINGEN</span><h2>Guild Wars Wiki-cache</h2><p>Afbeeldingen worden bij het eerste gebruik automatisch lokaal gecachet.</p></div></div><div class="image-catalog">
<?php foreach($imageItems as $item=>$wiki): ?><article><img src="/item-image.php?item=<?=rawurlencode($item)?>&size=64" alt=""><div><strong><?=h($item)?></strong><small><?=h($wiki)?></small></div></article><?php endforeach; ?>
</div></section>
