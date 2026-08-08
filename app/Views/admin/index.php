<?php
declare(strict_types=1);
$dq = $dataQuality['summary'] ?? [];
$assets = $assetSummary ?? ['imports'=>0,'assets'=>0,'linked'=>0,'unlinked'=>0,'files'=>0,'linked_items'=>0,'market_items'=>0,'unlinked_items'=>0];
?>

<section class="page-intro">
    <div>
        <span class="kicker">LITTYWATCH CONTROL CENTER</span>
        <h1>Admin</h1>
        <p>Alle beheerfuncties vanaf één plek. Deze ingang kan later als geheel achter authenticatie worden gezet.</p>
    </div>
</section>

<section class="asset-health">
    <div class="asset-health-icon">GW</div>
    <div class="asset-health-copy">
        <span class="kicker">INVENTORY ICONS</span>
        <h3>Lokale Guild Wars-itemicons</h3>
        <p><?= (int)($assets['files'] ?? 0) ?> echte Gw.dat inventory icons zijn meegeleverd. Geen Wiki-thumbnails op de spelerspagina's.</p>
    </div>
    <div class="asset-health-stats">
        <span>Bestanden<strong><?= (int)($assets['files'] ?? 0) ?></strong></span>
        <span>Geïndexeerd<strong><?= (int)($assets['assets'] ?? 0) ?></strong></span>
        <span>Marketitems met icoon<strong><?= (int)($assets['linked_items'] ?? 0) ?></strong></span>
        <span>Zonder icoon<strong><?= (int)($assets['unlinked_items'] ?? 0) ?></strong></span>
    </div>
    <a class="btn" href="/game-assets">Icons beheren</a>
</section>

<div class="admin-control-grid">
    <section class="admin-control-card">
        <span class="kicker">DATA</span>
        <h2>Kamadan & markt</h2>
        <p>Ophalen, verwerken en opnieuw opbouwen.</p>
        <div class="tool-list">
            <a href="/admin/collect"><strong>Kamadan nu ophalen</strong><span>Direct de nieuwste chatberichten ophalen.</span></a>
            <a href="/admin/dataset"><strong>Dataset & training</strong><span>Dekking, patronen en NDJSON-export bekijken.</span></a>
            <a href="/admin/reparse"><strong>Alles opnieuw parsen</strong><span>Herbouw structured offers en de itemmarkt.</span></a>
            <a href="/admin/market-maintenance"><strong>Market maintenance</strong><span>Lifecycle en prijsvertrouwen opnieuw berekenen.</span></a>
        </div>
    </section>

    <section class="admin-control-card">
        <span class="kicker">PARSER</span>
        <h2>Kwaliteit & learning</h2>
        <p>Twijfelgevallen oplossen en herkenning verbeteren.</p>
        <div class="tool-list">
            <a href="/parser-review"><strong>Parser Review</strong><span>Openstaande herkenningen beoordelen en corrigeren.</span></a>
            <a href="/admin/data-quality"><strong>Data Quality</strong><span>Onzekere prijzen, outliers en zwakke markten.</span></a>
            <a href="/admin/parser-lab"><strong>Parser Lab</strong><span>Losse Kamadan-zinnen testen.</span></a>
            <a href="/structured-offers"><strong>Structured Offers</strong><span>De uiteindelijke parseroutput inspecteren.</span></a>
        </div>
    </section>

    <section class="admin-control-card">
        <span class="kicker">KENNIS & SYSTEEM</span>
        <h2>Catalogus & assets</h2>
        <p>Itemkennis, echte inventory icons en techniek.</p>
        <div class="tool-list">
            <a href="/knowledge-pack"><strong>Knowledge Pack</strong><span>Wiki-categorieën, staging, aliases en itemkennis.</span></a>
            <a href="/admin/knowledge-seed"><strong>Knowledge Base seeden</strong><span>Lokale itemprofielen opnieuw installeren.</span></a>
            <a href="/game-assets"><strong>Inventory icons</strong><span>Automatisch herkennen, DAT file IDs controleren en uitzonderingen corrigeren.</span></a>
            <a href="/system"><strong>Systeemstatus</strong><span>Runtime, database en technische status.</span></a>
        </div>
    </section>
</div>

<section class="surface">
    <div class="section-heading">
        <div>
            <span class="kicker">DATA QUALITY</span>
            <h2>Gezondheid van de marktdata</h2>
            <p>Snelle controle zonder eerst naar een aparte beheerpagina te hoeven.</p>
        </div>
        <div class="actions"><a class="btn secondary" href="/admin/data-quality">Details bekijken</a></div>
    </div>
    <div class="metric-grid">
        <article class="metric"><span>Aanbiedingen</span><strong><?= (int)($dq['offers'] ?? 0) ?></strong></article>
        <article class="metric"><span>Betrouwbare prijzen</span><strong><?= (int)($dq['trusted_prices'] ?? 0) ?></strong></article>
        <article class="metric"><span>Zonder geldprijs</span><strong><?= (int)($dq['unpriced'] ?? 0) ?></strong></article>
        <article class="metric"><span>Parser review</span><strong><?= (int)($dq['parser_review'] ?? 0) ?></strong></article>
        <article class="metric"><span>Onzekere prijzen</span><strong><?= (int)($dq['uncertain_prices'] ?? 0) ?></strong></article>
        <article class="metric"><span>Outliers</span><strong><?= (int)($dq['outlier_prices'] ?? 0) ?></strong></article>
    </div>
</section>

<div class="twocol">
    <section class="surface">
        <div class="section-heading"><div><span class="kicker">AANDACHT NODIG</span><h2>Meest voorkomende problemen</h2></div></div>
        <?php if (empty($dataQuality['issues'])): ?>
            <p class="muted">Geen kwaliteitsproblemen gevonden.</p>
        <?php else: ?>
            <div class="tablewrap"><table><thead><tr><th>Probleem</th><th>Aantal</th></tr></thead><tbody>
            <?php foreach (array_slice($dataQuality['issues'], 0, 10) as $issue): ?>
                <tr><td><a class="itemlink" href="/admin/data-quality?category=<?= rawurlencode((string)$issue['issue_key']) ?>"><?= h((string)$issue['label']) ?></a></td><td><strong><?= (int)$issue['total'] ?></strong></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>

    <section class="surface">
        <div class="section-heading"><div><span class="kicker">MARKET TRUST</span><h2>Zwakste actieve markten</h2></div></div>
        <?php if (empty($dataQuality['weak_markets'])): ?>
            <p class="muted">Nog onvoldoende marktdata.</p>
        <?php else: ?>
            <div class="tablewrap"><table><thead><tr><th>Item</th><th>Score</th><th>Traders</th></tr></thead><tbody>
            <?php foreach (array_slice($dataQuality['weak_markets'], 0, 10) as $market): $trust = $market['trust']; ?>
                <tr>
                    <td><a class="itemlink" href="/item?name=<?= rawurlencode((string)$market['item']) ?>"><?= h((string)$market['item']) ?></a></td>
                    <td><strong><?= (int)$trust['score'] ?>/100</strong><div class="muted"><?= h((string)$trust['label']) ?></div></td>
                    <td><?= (int)$trust['traders'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
</div>
