<?php
declare(strict_types=1);

$total = (int)($qualityStats['total'] ?? 0);
$pct = static fn(mixed $value): string => $total > 0
    ? number_format(((int)$value / $total) * 100, 1, ',', '.') . '%'
    : '0%';
$releaseFile = dirname(__DIR__, 2) . '/Data/parser-release.json';
$parserRelease = '';
if (is_file($releaseFile)) {
    $releaseData = json_decode((string)file_get_contents($releaseFile), true);
    $parserRelease = trim((string)($releaseData['release'] ?? ''));
}

$tabs = [
    'queue' => 'Review Queue',
    'aliases' => 'Aliases',
    'exclusions' => 'Uitsluitingen',
    'sets' => 'Setgroottes',
    'knowledge' => 'Itemkennis',
    'corrections' => 'Correcties',
];
?>
<section class="page-intro">
  <div>
    <span class="kicker">PARSER LEARNING</span>
    <h1>Parser Review</h1>
    <p>Werk twijfelgevallen snel af en bouw een lokale Guild Wars-kennisbank op.</p>
    <?php if ($parserRelease !== ''): ?><p class="muted">Parser release: <?= h($parserRelease) ?></p><?php endif; ?>
  </div>
  <div class="actions">
    <button class="btn" type="button" data-rereview-start>Herbeoordeel openstaande berichten</button>
    <a class="btn secondary" href="/parser-review/export">Exporteren</a>
  </div>
</section>

<?php if ($message): ?><div class="flash success"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash error"><?= h($error) ?></div><?php endif; ?>

<section class="stats-grid parser-stats">
  <article><span>Berichten</span><strong><?= number_format($total, 0, ',', '.') ?></strong></article>
  <article><span>Herkend</span><strong><?= $pct($qualityStats['parsed'] ?? 0) ?></strong></article>
  <article><span>Review</span><strong><?= $pct($qualityStats['review'] ?? 0) ?></strong></article>
  <article><span>Uitgesloten</span><strong><?= $pct($qualityStats['excluded'] ?? 0) ?></strong></article>
</section>

<section class="surface batch-review-panel" data-rereview-panel hidden>
  <div class="section-heading">
    <div>
      <span class="kicker">BATCH HERBEOORDELING</span>
      <h2>Openstaande berichten opnieuw beoordelen</h2>
    </div>
    <strong data-rereview-percent>0%</strong>
  </div>
  <div class="batch-progress"><span data-rereview-bar></span></div>
  <p data-rereview-status>Voorbereiden…</p>
  <div class="batch-result-grid">
    <span>Gecontroleerd <strong data-rereview-checked>0</strong></span>
    <span>Herkend <strong data-rereview-parsed>0</strong></span>
    <span>Uitgesloten <strong data-rereview-excluded>0</strong></span>
    <span>Review <strong data-rereview-review>0</strong></span>
    <span>Mislukt <strong data-rereview-failed>0</strong></span>
  </div>
</section>

<?php if (!empty($reasonGroups)): ?>
<section class="surface v5-reason-panel">
  <div class="section-heading">
    <div><span class="kicker">V5 DIAGNOSTIEK</span><h2>Resterende reviewredenen</h2></div>
  </div>
  <div class="v5-reason-grid">
    <?php foreach ($reasonGroups as $reason): ?>
      <span><strong><?= h($reason['reason']) ?></strong><?= (int)$reason['total'] ?></span>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<nav class="review-tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="<?= $tab === $key ? 'active' : '' ?>"
       href="/parser-review?tab=<?= h($key) ?>&status=<?= h($status) ?>">
      <?= h($label) ?>
    </a>
  <?php endforeach; ?>
</nav>


<style>
#lw-review-workbench{
  display:flex !important;
  flex-direction:row !important;
  align-items:flex-start !important;
  gap:16px !important;
  width:100% !important;
  min-width:0 !important;
}
#lw-review-workbench > #lw-review-list{
  flex:0 0 390px !important;
  width:390px !important;
  min-width:320px !important;
  max-width:390px !important;
  position:sticky !important;
  top:86px !important;
  max-height:calc(100vh - 105px) !important;
  overflow:hidden !important;
}
#lw-review-workbench > #lw-review-detail{
  flex:1 1 auto !important;
  width:auto !important;
  min-width:0 !important;
  position:sticky !important;
  top:86px !important;
  max-height:calc(100vh - 105px) !important;
  overflow:auto !important;
}
#lw-review-list .lw-review-items{
  max-height:calc(100vh - 245px) !important;
  overflow-y:auto !important;
  overflow-x:hidden !important;
}
@media (max-width:760px){
  #lw-review-workbench{
    display:block !important;
  }
  #lw-review-workbench > #lw-review-list,
  #lw-review-workbench > #lw-review-detail{
    width:100% !important;
    max-width:none !important;
    min-width:0 !important;
    position:static !important;
    max-height:none !important;
  }
  #lw-review-workbench > #lw-review-detail{
    margin-top:16px !important;
  }
  #lw-review-list .lw-review-items{
    max-height:420px !important;
  }
}
</style>

<?php if ($tab === 'queue'): ?>
<div id="lw-review-workbench" class="lw-review-split">
  <section id="lw-review-list" class="lw-review-list-panel surface">
    <form class="lw-review-filter" method="get">
      <input type="hidden" name="tab" value="queue">
      <select name="status">
        <?php foreach (['pending','approved','corrected','rejected'] as $value): ?>
          <option value="<?= h($value) ?>" <?= $status === $value ? 'selected' : '' ?>>
            <?= h(ucfirst($value)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <input name="q" value="<?= h($query) ?>" placeholder="Zoek speler, bericht of item">
      <button class="btn small">Zoeken</button>
    </form>

    <div class="lw-review-count"><?= count($rows) ?> zichtbaar</div>

    <div class="lw-review-items">
      <?php if (!$rows): ?><div class="empty-inline">Geen reviewregels gevonden.</div><?php endif; ?>
      <?php foreach ($rows as $row): ?>
        <a class="lw-review-item <?= $selected && (int)$selected['id'] === (int)$row['id'] ? 'active' : '' ?>"
           href="/parser-review?tab=queue&status=<?= h($status) ?>&q=<?= rawurlencode($query) ?>&selected=<?= (int)$row['id'] ?>">
          <div class="queue-head">
            <strong><?= h($row['player']) ?></strong>
            <span><?= number_format((float)$row['confidence'] * 100, 0) ?>%</span>
          </div>
          <p><?= h(mb_strimwidth((string)$row['message'], 0, 115, '…')) ?></p>
          <div class="queue-meta">
            <span><?= h($row['item']) ?></span>
            <span><?= h($row['quality_reason']) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="lw-review-detail" class="lw-review-detail-panel surface">
    <?php if (!$selected): ?>
      <div class="empty-state">
        <h2>Selecteer een bericht</h2>
        <p>Kies links een reviewregel om de parseruitvoer te bekijken.</p>
      </div>
    <?php else:
      $expected = json_decode((string)($selected['expected_json'] ?? ''), true) ?: [];
    ?>
      <header class="lw-review-detail-header">
        <div>
          <span class="kicker">ORIGINEEL BERICHT</span>
          <h2><?= h($selected['player']) ?></h2>
          <small><?= h($selected['posted_at']) ?></small>
        </div>
        <span class="confidence-badge"><?= number_format((float)$selected['confidence'] * 100, 0) ?>%</span>
      </header>

      <blockquote class="original-message"><?= h($selected['message']) ?></blockquote>

      <div class="parser-result-grid">
        <div><span>Parser-item</span><strong><?= h($selected['item']) ?></strong></div>
        <div><span>Marktkey</span><strong><?= h($selected['market_key'] ?: '—') ?></strong></div>
        <div><span>Requirement</span><strong><?= h($selected['requirement'] ?: '—') ?></strong></div>
        <div><span>Attribuut</span><strong><?= h($selected['attribute_name'] ?: '—') ?></strong></div>
        <div class="wide"><span>Segment</span><code><?= h($selected['raw_segment']) ?></code></div>
      </div>

      <?php if ($selectedKnowledge): ?>
        <article class="knowledge-summary <?= (int)$selectedKnowledge['is_unique'] === 1 ? 'unique' : 'rare' ?>">
          <div>
            <span class="kicker">LOKALE ITEMKENNIS</span>
            <h3><?= h($selectedKnowledge['item_name']) ?></h3>
          </div>
          <div class="knowledge-badges">
            <span><?= h(ucfirst($selectedKnowledge['rarity'])) ?></span>
            <span><?= (int)$selectedKnowledge['fixed_stats'] === 1 ? 'Vaste stats' : 'Variabele stats' ?></span>
            <span><?= (int)$selectedKnowledge['modifiable'] === 1 ? 'Modificeerbaar' : 'Niet modificeerbaar' ?></span>
          </div>
        </article>
      <?php endif; ?>

      <div class="quick-actions">
        <form method="post">
          <input type="hidden" name="action" value="review">
          <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
          <input type="hidden" name="selected" value="<?= (int)$selected['id'] ?>">
          <input type="hidden" name="return_tab" value="queue">
          <input type="hidden" name="return_status" value="<?= h($status) ?>">
          <input type="hidden" name="review_status" value="approved">
          <input type="hidden" name="expected_item" value="<?= h($selected['item']) ?>">
          <button class="btn success-button">✓ Parser klopt</button>
        </form>
        <button class="btn secondary" type="button" data-open-correction>✎ Corrigeren</button>
        <button class="btn secondary" type="button" data-open-wiki>⌕ Wiki zoeken</button>
      </div>

      <details class="correction-panel" data-correction-panel>
        <summary>Handmatig corrigeren</summary>
        <form method="post" class="detail-form">
          <input type="hidden" name="action" value="review">
          <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
          <input type="hidden" name="selected" value="<?= (int)$selected['id'] ?>">
          <input type="hidden" name="return_tab" value="queue">
          <input type="hidden" name="return_status" value="<?= h($status) ?>">
          <input type="hidden" name="review_status" value="corrected">

          <label>Correct item
            <input name="expected_item" value="<?= h($expected['item'] ?? $selected['item']) ?>" required>
          </label>
          <label>Alias leren
            <input name="alias" placeholder="Madruks Prophecy">
          </label>
          <div class="form-pair">
            <label>Requirement
              <input name="expected_requirement" type="number" min="0" value="<?= h($expected['requirement'] ?? '') ?>">
            </label>
            <label>Attribuut
              <input name="expected_attribute" value="<?= h($expected['attribute'] ?? '') ?>">
            </label>
          </div>
          <label>Market key
            <input name="expected_market_key" value="<?= h($expected['market_key'] ?? $selected['market_key']) ?>">
          </label>
          <label>Notitie
            <textarea name="notes" rows="3"><?= h($selected['notes'] ?? '') ?></textarea>
          </label>
          <button class="btn">Opslaan en leren</button>
        </form>
      </details>

      <details class="wiki-panel" data-wiki-panel>
        <summary>Guild Wars Wiki-kennis overnemen</summary>
        <div class="wiki-lookup">
          <div class="wiki-search-row">
            <input data-wiki-query value="<?= h($expected['item'] ?? $selected['item']) ?>">
            <button class="btn" type="button" data-wiki-search>Wiki zoeken</button>
            <a class="btn secondary" target="_blank" rel="noopener"
               data-wiki-external
               href="https://wiki.guildwars.com/wiki/Special:Search?search=<?= rawurlencode((string)($expected['item'] ?? $selected['item'])) ?>">
              Open Wiki
            </a>
          </div>
          <div class="wiki-results" data-wiki-results>
            <p class="muted">De zoekopdracht draait vanuit je browser. Daardoor gebruikt hij niet het eerder geblokkeerde server-IP.</p>
          </div>
        </div>

        <form method="post" class="detail-form" data-knowledge-form>
          <input type="hidden" name="action" value="save_item_knowledge">
          <input type="hidden" name="return_tab" value="queue">
          <input type="hidden" name="return_status" value="<?= h($status) ?>">
          <input type="hidden" name="selected" value="<?= (int)$selected['id'] ?>">
          <input type="hidden" name="source_status" value="wiki-confirmed">

          <label>Itemnaam
            <input name="item_name" value="<?= h($expected['item'] ?? $selected['item']) ?>" required>
          </label>
          <div class="form-pair">
            <label>Wiki-titel<input name="wiki_title" value="<?= h($selectedKnowledge['wiki_title'] ?? '') ?>"></label>
            <label>Wiki-URL<input name="wiki_url" value="<?= h($selectedKnowledge['wiki_url'] ?? '') ?>"></label>
          </div>
          <label>Wiki-samenvatting
            <textarea name="wiki_extract" rows="4"><?= h($selectedKnowledge['wiki_extract'] ?? '') ?></textarea>
          </label>
          <div class="form-triple">
            <label>Rarity
              <select name="rarity">
                <?php foreach (['unknown','unique','rare','uncommon','common'] as $value): ?>
                  <option value="<?= h($value) ?>" <?= ($selectedKnowledge['rarity'] ?? '') === $value ? 'selected' : '' ?>>
                    <?= h(ucfirst($value)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Itemtype<input name="item_class" value="<?= h($selectedKnowledge['item_class'] ?? '') ?>" placeholder="staff, sword, miniature"></label>
            <label>Eigenschappen
              <span class="checkbox-stack">
                <label><input type="checkbox" name="is_unique" value="1" <?= !empty($selectedKnowledge['is_unique']) ? 'checked' : '' ?>> Unique/green</label>
                <label><input type="checkbox" name="fixed_stats" value="1" <?= !empty($selectedKnowledge['fixed_stats']) ? 'checked' : '' ?>> Vaste stats</label>
                <label><input type="checkbox" name="modifiable" value="1" <?= !isset($selectedKnowledge['modifiable']) || !empty($selectedKnowledge['modifiable']) ? 'checked' : '' ?>> Modificeerbaar</label>
              </span>
            </label>
          </div>
          <label>Vaste stats, één per regel
            <textarea name="canonical_stats" rows="5"><?=
              h(isset($selectedKnowledge['canonical_stats'])
                ? implode("\n", $selectedKnowledge['canonical_stats'])
                : '')
            ?></textarea>
          </label>
          <button class="btn">Itemkennis lokaal opslaan</button>
        </form>
      </details>
    <?php endif; ?>
  </section>
</div>

<?php elseif ($tab === 'aliases'): ?>
  <section class="surface knowledge-page">
    <div class="section-heading"><div><span class="kicker">PARSER LEARNING</span><h2>Aliases</h2></div></div>
    <form method="post" class="knowledge-add">
      <input type="hidden" name="action" value="add_alias">
      <input type="hidden" name="return_tab" value="aliases">
      <input name="alias" placeholder="obby edge" required>
      <input name="item_name" placeholder="Obsidian Edge" required>
      <button class="btn">Toevoegen</button>
    </form>
    <div class="knowledge-table">
      <?php foreach ($knowledge['aliases'] as $row): ?>
        <form method="post">
          <input type="hidden" name="action" value="delete_alias">
          <input type="hidden" name="return_tab" value="aliases">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <span><strong><?= h($row['alias']) ?></strong> → <?= h($row['item_name']) ?></span>
          <button class="icon-button">×</button>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

<?php elseif ($tab === 'exclusions'): ?>
  <section class="surface knowledge-page">
    <div class="section-heading"><div><span class="kicker">FILTERS</span><h2>Uitsluitingen</h2></div></div>
    <form method="post" class="knowledge-add">
      <input type="hidden" name="action" value="add_exclusion">
      <input type="hidden" name="return_tab" value="exclusions">
      <input name="phrase" placeholder="fow armor" required>
      <select name="kind"><option value="noise">Noise</option><option value="service">Service</option><option value="character_name_sale">Naamverkoop</option></select>
      <button class="btn">Toevoegen</button>
    </form>
    <div class="knowledge-table">
      <?php foreach ($knowledge['exclusions'] as $row): ?>
        <form method="post">
          <input type="hidden" name="action" value="delete_exclusion">
          <input type="hidden" name="return_tab" value="exclusions">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <span><strong><?= h($row['phrase']) ?></strong> · <?= h($row['kind']) ?></span>
          <button class="icon-button">×</button>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

<?php elseif ($tab === 'sets'): ?>
  <section class="surface knowledge-page">
    <div class="section-heading"><div><span class="kicker">HOEVEELHEDEN</span><h2>Setgroottes</h2></div></div>
    <form method="post" class="knowledge-add">
      <input type="hidden" name="action" value="add_set_size">
      <input type="hidden" name="return_tab" value="sets">
      <input name="item_name" placeholder="Rin Relic" required>
      <input name="set_size" type="number" min="1" placeholder="25" required>
      <button class="btn">Toevoegen</button>
    </form>
    <div class="knowledge-table">
      <?php foreach ($knowledge['set_sizes'] as $row): ?>
        <form method="post">
          <input type="hidden" name="action" value="delete_set_size">
          <input type="hidden" name="return_tab" value="sets">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <span><strong><?= h($row['item_name']) ?></strong> = <?= h($row['set_size']) ?></span>
          <button class="icon-button">×</button>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

<?php elseif ($tab === 'knowledge'): ?>
  <section class="surface knowledge-page">
    <div class="section-heading">
      <div><span class="kicker">ENCYCLOPEDIE</span><h2>Lokale itemkennis</h2></div>
      <form method="get" class="filters">
        <input type="hidden" name="tab" value="knowledge">
        <input name="knowledge_q" placeholder="Zoek itemkennis">
        <button class="btn small">Zoeken</button>
      </form>
    </div>
    <div class="item-knowledge-grid">
      <?php foreach ($itemKnowledgeRows as $row): ?>
        <article class="item-knowledge-card">
          <div class="knowledge-card-head">
            <div><strong><?= h($row['item_name']) ?></strong><small><?= h($row['item_class'] ?: 'Onbekend type') ?></small></div>
            <span class="rarity <?= h($row['rarity']) ?>"><?= h(ucfirst($row['rarity'])) ?></span>
          </div>
          <p><?= h($row['wiki_extract'] ?: 'Geen samenvatting opgeslagen.') ?></p>
          <div class="knowledge-badges">
            <span><?= (int)$row['fixed_stats'] === 1 ? 'Vaste stats' : 'Variabel' ?></span>
            <span><?= (int)$row['modifiable'] === 1 ? 'Modificeerbaar' : 'Niet modificeerbaar' ?></span>
          </div>
          <div class="actions">
            <?php if ($row['wiki_url']): ?><a class="btn small secondary" href="<?= h($row['wiki_url']) ?>" target="_blank" rel="noopener">Wiki</a><?php endif; ?>
            <form method="post">
              <input type="hidden" name="action" value="delete_item_knowledge">
              <input type="hidden" name="return_tab" value="knowledge">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button class="btn small danger">Verwijderen</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

<?php else: ?>
  <section class="surface knowledge-page">
    <span class="kicker">GESCHIEDENIS</span>
    <h2>Correcties</h2>
    <div class="tablewrap">
      <table>
        <thead><tr><th>Moment</th><th>Actie</th><th>Alias</th><th>Item</th><th>Notitie</th></tr></thead>
        <tbody>
          <?php foreach ($knowledge['corrections'] as $row): ?>
            <tr><td><?= h($row['created_at']) ?></td><td><?= h($row['action']) ?></td><td><?= h($row['alias']) ?></td><td><?= h($row['item_name']) ?></td><td><?= h($row['notes']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<script>
(() => {
  const start = document.querySelector('[data-rereview-start]');
  const panel = document.querySelector('[data-rereview-panel]');
  const bar = document.querySelector('[data-rereview-bar]');
  const percent = document.querySelector('[data-rereview-percent]');
  const status = document.querySelector('[data-rereview-status]');
  const fields = {
    checked: document.querySelector('[data-rereview-checked]'),
    parsed: document.querySelector('[data-rereview-parsed]'),
    excluded: document.querySelector('[data-rereview-excluded]'),
    review: document.querySelector('[data-rereview-review]'),
    failed: document.querySelector('[data-rereview-failed]'),
  };

  let running = false;
  let cursor = 0;
  let totals = {checked:0, parsed:0, excluded:0, review:0, failed:0};
  let initialEstimate = 0;

  const paint = remaining => {
    Object.entries(fields).forEach(([key, element]) => {
      if (element) element.textContent = String(totals[key] || 0);
    });
    if (initialEstimate <= 0) initialEstimate = totals.checked + remaining;
    const done = initialEstimate > 0
      ? Math.min(100, Math.round((totals.checked / initialEstimate) * 100))
      : 0;
    if (bar) bar.style.width = done + '%';
    if (percent) percent.textContent = done + '%';
  };

  const runBatch = async () => {
    const body = new URLSearchParams({cursor:String(cursor), limit:'150'});
    const response = await fetch('/parser-review/re-evaluate', {
      method:'POST',
      headers:{
        'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept':'application/json'
      },
      body
    });

    const raw = await response.text();
    let result;

    try {
      result = JSON.parse(raw);
    } catch (parseError) {
      const plain = raw
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<[^>]+>/g, '')
        .trim();

      throw new Error(
        plain
          ? 'Server gaf geen geldige JSON terug: ' + plain.slice(0, 800)
          : 'Server gaf een lege of ongeldige response terug.'
      );
    }

    if (!response.ok || !result.ok) {
      const location = result.error_location
        ? ` (${result.error_location})`
        : '';

      throw new Error(
        (result.error || 'Batch-herbeoordeling mislukt.') + location
      );
    }

    for (const key of Object.keys(totals)) {
      totals[key] += Number(result[key] || 0);
    }
    cursor = Number(result.next_cursor || cursor);
    paint(Number(result.remaining || 0));

    if (status) {
      const samples = Array.isArray(result.failure_samples)
        ? result.failure_samples.filter(Boolean)
        : [];

      const release = result.parser_release ? ` [${result.parser_release}]` : '';
      if (samples.length) {
        status.textContent = 'Batch verwerkt met fouten' + release + ': ' + samples.join(' | ');
      } else {
        status.textContent = result.done
          ? 'Herbeoordeling voltooid' + release + '. De Review Queue wordt vernieuwd.'
          : `${result.remaining} berichten wachten nog. Volgende batch wordt verwerkt…${release}`;
      }
    }

    if (!result.done) {
      await runBatch();
      return;
    }

    if (bar) bar.style.width = '100%';
    if (percent) percent.textContent = '100%';
    setTimeout(() => window.location.reload(), 1200);
  };

  start?.addEventListener('click', async () => {
    if (running) return;
    if (!window.confirm('Alle openstaande reviewberichten opnieuw beoordelen met de nieuwste parserregels?')) return;

    running = true;
    cursor = 0;
    totals = {checked:0, parsed:0, excluded:0, review:0, failed:0};
    initialEstimate = 0;
    start.disabled = true;
    panel.hidden = false;
    if (status) status.textContent = 'Eerste batch wordt verwerkt…';

    try {
      await runBatch();
    } catch (error) {
      if (status) status.textContent = error.message;
      start.disabled = false;
      running = false;
    }
  });
})();
</script>

<script>
(() => {
  const correctionButton = document.querySelector('[data-open-correction]');
  const correctionPanel = document.querySelector('[data-correction-panel]');
  correctionButton?.addEventListener('click', () => {
    if (correctionPanel) correctionPanel.open = true;
    correctionPanel?.scrollIntoView({behavior:'smooth', block:'start'});
  });

  const wikiButton = document.querySelector('[data-open-wiki]');
  const wikiPanel = document.querySelector('[data-wiki-panel]');
  wikiButton?.addEventListener('click', () => {
    if (wikiPanel) wikiPanel.open = true;
    wikiPanel?.scrollIntoView({behavior:'smooth', block:'start'});
  });

  const searchButton = document.querySelector('[data-wiki-search]');
  const queryInput = document.querySelector('[data-wiki-query]');
  const results = document.querySelector('[data-wiki-results]');
  const form = document.querySelector('[data-knowledge-form]');
  const external = document.querySelector('[data-wiki-external]');

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  })[char]);

  searchButton?.addEventListener('click', async () => {
    const query = queryInput?.value.trim();
    if (!query || !results || !form) return;

    external.href = 'https://wiki.guildwars.com/wiki/Special:Search?search=' + encodeURIComponent(query);
    results.innerHTML = '<p class="muted">Wiki wordt doorzocht…</p>';

    const endpoint = new URL('https://wiki.guildwars.com/api.php');
    endpoint.searchParams.set('action','query');
    endpoint.searchParams.set('generator','search');
    endpoint.searchParams.set('gsrsearch',query);
    endpoint.searchParams.set('gsrlimit','5');
    endpoint.searchParams.set('prop','extracts|info');
    endpoint.searchParams.set('exintro','1');
    endpoint.searchParams.set('explaintext','1');
    endpoint.searchParams.set('inprop','url');
    endpoint.searchParams.set('format','json');
    endpoint.searchParams.set('origin','*');

    try {
      const response = await fetch(endpoint.toString(), {headers:{'Accept':'application/json'}});
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const data = await response.json();
      const pages = Object.values(data?.query?.pages ?? {}).sort((a,b) => (a.index ?? 999) - (b.index ?? 999));

      if (!pages.length) {
        results.innerHTML = '<p>Geen Wiki-resultaten gevonden.</p>';
        return;
      }

      results.innerHTML = pages.map((page, index) => `
        <button type="button" class="wiki-result" data-wiki-result="${index}">
          <strong>${escapeHtml(page.title)}</strong>
          <span>${escapeHtml((page.extract || '').slice(0, 260))}</span>
        </button>
      `).join('');

      results.querySelectorAll('[data-wiki-result]').forEach(button => {
        button.addEventListener('click', () => {
          const page = pages[Number(button.dataset.wikiResult)];
          form.elements.wiki_title.value = page.title || '';
          form.elements.wiki_url.value = page.fullurl || '';
          form.elements.wiki_extract.value = page.extract || '';
          form.elements.item_name.value = page.title || query;

          const text = ((page.title || '') + ' ' + (page.extract || '')).toLowerCase();
          const unique = text.includes('unique item') || text.includes('green item') || text.includes('green weapon');
          if (unique) {
            form.elements.rarity.value = 'unique';
            form.elements.is_unique.checked = true;
            form.elements.fixed_stats.checked = true;
            form.elements.modifiable.checked = false;
          }

          results.querySelectorAll('.wiki-result').forEach(element => element.classList.remove('selected'));
          button.classList.add('selected');
        });
      });
    } catch (error) {
      results.innerHTML = `
        <div class="wiki-error">
          <strong>De Wiki-aanvraag werd geblokkeerd.</strong>
          <p>Open de Wiki via de knop en vul de gegevens handmatig in. De lokale kennisbank blijft gewoon werken.</p>
        </div>
      `;
    }
  });
})();
</script>


