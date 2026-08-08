<?php
declare(strict_types=1);
$title = 'Dashboard · LittyWatch';

$fmt = static function (float $value, int $maxDecimals = 2): string {
    $decimals = abs($value - round($value)) < 0.0001 ? 0 : $maxDecimals;
    return number_format($value, $decimals, ',', '.');
};
$rateImages = [
    'gold_to_ecto' => 'Glob of Ectoplasm',
    'ecto_to_armbrace' => 'Armbrace of Truth',
    'ecto_to_zkey' => 'Zaishen Key',
    'ecto_to_obby' => 'Obsidian Shard',
];
$rateShort = [
    'gold_to_ecto' => ['Platinum / Ecto', 'Gold naar ectoplasm'],
    'ecto_to_armbrace' => ['Ecto / Armbrace', 'Ectoplasm naar armbrace'],
    'ecto_to_zkey' => ['Ecto / Zkey', 'Ectoplasm naar Zaishen Key'],
    'ecto_to_obby' => ['Ecto / Obby', 'Ectoplasm naar Obsidian Shard'],
];
$latestAt = (string)($exchangeRates['updated_at'] ?? ($counters['latest_posted_at'] ?? ''));
if ($latestAt === '' || $latestAt === '-') {
    $latestAt = (string)($counters['latest_posted_at'] ?? '');
}
?>

<section class="market-hero">
    <div class="market-hero-copy">
        <span class="kicker">KAMADAN · AMERICA ENGLISH 1</span>
        <h1>De markt, zonder ruis.</h1>
        <p>Actuele koersen, betrouwbare prijsbewegingen en de nieuwste aanbiedingen uit Kamadan.</p>
    </div>
    <div class="market-live-state">
        <span class="live-dot" aria-hidden="true"></span>
        <div>
            <strong>Marktdata actief</strong>
            <small><?= $latestAt !== '' ? 'Laatste koersdata · '.h(lw_local_datetime($latestAt)) : 'Wacht op marktdata' ?></small>
        </div>
    </div>
</section>

<section class="market-overview">
    <article class="exchange-board">
        <header class="board-heading">
            <div>
                <span class="kicker">BELANGRIJKSTE KOERSEN</span>
                <h2>Exchange rates</h2>
            </div>
            <span class="board-source"><?= h((string)($exchangeRates['source'] ?? '')) ?></span>
        </header>

        <div class="rate-grid">
            <?php foreach (($exchangeRates['rates'] ?? []) as $rate):
                $key = (string)($rate['key'] ?? '');
                $meta = $rateShort[$key] ?? [(string)($rate['label'] ?? $key), ''];
                $imageItem = $rateImages[$key] ?? (string)($rate['right_unit'] ?? '');
                $isLive = !empty($rate['live']);
            ?>
                <article class="rate-card <?= $isLive ? 'is-live' : 'is-fallback' ?>">
                    <div class="rate-card-top">
                        <span class="inventory-icon inventory-icon-rate">
                            <img src="/item-image.php?item=<?= rawurlencode($imageItem) ?>&size=72" alt="" loading="lazy">
                        </span>
                        <span class="rate-status <?= $isLive ? 'live' : 'fallback' ?>">
                            <?= $isLive ? 'LIVE' : 'FALLBACK' ?>
                        </span>
                    </div>
                    <div class="rate-name">
                        <strong><?= h($meta[0]) ?></strong>
                        <small><?= h($meta[1]) ?></small>
                    </div>
                    <div class="rate-equation">
                        <span><?= $fmt((float)$rate['left_amount']) ?> <?= h((string)$rate['left_unit']) ?></span>
                        <b>≈</b>
                        <strong><?= $fmt((float)$rate['right_amount']) ?> <?= h((string)$rate['right_unit']) ?></strong>
                    </div>
                    <div class="rate-foot">
                        <?php if ($isLive): ?>
                            Mediaan van <?= (int)($rate['samples'] ?? 0) ?> onafhankelijke traders
                        <?php else: ?>
                            Veilige richtwaarde tot er genoeg betrouwbare data is
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </article>

    <div class="mover-board">
        <?php foreach ([
            'gainer' => ['Hardste stijger', 'Stijging in de laatste 24 uur', '▲'],
            'loser' => ['Hardste daler', 'Daling in de laatste 24 uur', '▼'],
        ] as $key => $meta):
            $mover = $movers[$key] ?? null;
        ?>
            <article class="movement-card <?= $key ?>">
                <header>
                    <div>
                        <span class="movement-label"><?= h($meta[0]) ?></span>
                        <small><?= h($meta[1]) ?></small>
                    </div>
                    <span class="movement-arrow" aria-hidden="true"><?= $meta[2] ?></span>
                </header>

                <?php if ($mover): ?>
                    <div class="movement-item">
                        <span class="inventory-icon inventory-icon-mover">
                            <img src="/item-image.php?item=<?= rawurlencode((string)$mover['item']) ?>&size=96" alt="" loading="lazy">
                        </span>
                        <div class="movement-copy">
                            <a href="/item?name=<?= rawurlencode((string)$mover['item']) ?>"><?= h((string)$mover['item']) ?></a>
                            <strong><?= ((float)$mover['percent'] >= 0 ? '+' : '') . number_format((float)$mover['percent'], 1, ',', '.') ?>%</strong>
                            <span>Nu ≈ <?= $fmt((float)$mover['current']) ?>e</span>
                        </div>
                    </div>
                    <footer>
                        <?= (int)($mover['traders_now'] ?? 0) ?> traders nu · <?= (int)($mover['traders_previous'] ?? 0) ?> vorige periode
                    </footer>
                <?php else: ?>
                    <div class="movement-empty">
                        <strong>Nog geen betrouwbaar signaal</strong>
                        <span>Er zijn minimaal twee onafhankelijke traders in beide 24-uursperiodes nodig.</span>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="surface latest-offers-panel">
    <div class="section-heading offers-heading">
        <div>
            <span class="kicker">LIVE UIT KAMADAN</span>
            <h2>Nieuwste aanbiedingen</h2>
            <p>Alleen actieve aanbiedingen die door de parser als betrouwbaar zijn geaccepteerd.</p>
        </div>
        <div class="actions"><a class="btn secondary" href="/items">Alle items bekijken →</a></div>
    </div>

    <?php if (empty($offers)): ?>
        <div class="dashboard-empty">Nog geen geaccepteerde aanbiedingen beschikbaar.</div>
    <?php else: ?>
        <div class="tablewrap offers-table-wrap">
            <table class="offers-table">
                <thead><tr><th>Offer</th><th>Item</th><th>Prijs</th><th>Marktmediaan</th><th>Speler</th><th>Datum</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($offers, 0, 16) as $offer):
                    $tradeType = strtolower((string)($offer['trade_type'] ?? 'trade'));
                    $priceCurrency = (string)($offer['price_currency'] ?? '');
                    $priceAmount = $offer['price_amount'] ?? null;
                    $basis = (string)($offer['price_basis'] ?? '');
                ?>
                    <tr>
                        <td><span class="trade-badge <?= h($tradeType) ?>"><?= h(strtoupper($tradeType)) ?></span></td>
                        <td>
                            <div class="offer-item-cell">
                                <span class="inventory-icon inventory-icon-table">
                                    <img src="/item-image.php?item=<?= rawurlencode((string)$offer['item']) ?>&size=64" alt="" loading="lazy">
                                </span>
                                <div>
                                    <a class="itemlink" href="/item?name=<?= rawurlencode((string)$offer['item']) ?>"><?= h((string)$offer['item']) ?></a>
                                    <?php if (!empty($offer['details']) && $offer['details'] !== 'Standaard'): ?>
                                        <small><?= h((string)$offer['details']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="offer-price">
                                <?php if ($basis === 'barter' && !empty($offer['exchange_item'])): ?>
                                    <strong><?= h((string)($offer['exchange_give_quantity'] ?? 1)) ?> : <?= h((string)($offer['exchange_receive_quantity'] ?? 1)) ?></strong>
                                    <small>voor <?= h((string)$offer['exchange_item']) ?></small>
                                <?php elseif ($priceAmount !== null && $priceCurrency !== ''): ?>
                                    <strong><?= h($fmt((float)$priceAmount) . $priceCurrency) ?></strong>
                                    <?php if (($offer['unit_price_ecto'] ?? null) !== null): ?>
                                        <small><?= $fmt((float)$offer['unit_price_ecto']) ?>e / stuk</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="muted">Prijs niet genoemd</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if (($offer['average_price_ecto'] ?? null) !== null): ?>
                                <strong class="market-median">≈ <?= $fmt((float)$offer['average_price_ecto']) ?>e</strong>
                            <?php else: ?>
                                <span class="muted">Onvoldoende data</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="player-name"><?= h((string)$offer['player']) ?></span></td>
                        <td class="offer-date"><?= h(lw_local_datetime((string)$offer['posted_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
