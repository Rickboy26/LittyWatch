<?php declare(strict_types=1); ?>
<section class="empty-state panel">
  <span class="kicker">ITEMS</span>
  <h1>Item niet gevonden</h1>
  <p>Er zijn geen geaccepteerde aanbiedingen gevonden voor <strong><?= h($name) ?></strong>.</p>
  <div class="actions">
    <a class="btn" href="/items?q=<?= rawurlencode((string)$name) ?>">Zoek vergelijkbare items</a>
    <a class="btn secondary" href="/live">Bekijk live aanbiedingen</a>
  </div>
</section>
