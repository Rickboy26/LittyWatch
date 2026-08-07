<?php declare(strict_types=1);
$profiles = $sources['profiles'] ?? [];
?>
<section class="page-intro">
  <div>
    <span class="kicker">GUILD WARS KNOWLEDGE PACK</span>
    <h1>Wiki-import & lokale catalogus</h1>
    <p>Haal gecontroleerde categorieën uit Guild Wars Wiki, controleer de staging en publiceer ze daarna lokaal naar de parser.</p>
  </div>
  <div class="actions">
    <a class="btn secondary" href="/knowledge">Knowledge Base</a>
    <a class="btn secondary" href="/parser-review">Parser Review</a>
  </div>
</section>

<?php if($message):?><div class="flash success"><?=h($message)?></div><?php endif;?>
<?php if($error):?><div class="flash error"><?=h($error)?></div><?php endif;?>

<section class="stats-grid">
  <article><span>Gepubliceerde items</span><strong><?=number_format((int)($metadata['item_count']??0),0,',','.')?></strong></article>
  <article><span>Aliassen</span><strong><?=number_format((int)($metadata['alias_count']??0),0,',','.')?></strong></article>
  <article><span>Stagingpagina’s</span><strong><?=number_format((int)$staged_count,0,',','.')?></strong></article>
  <article><span>Laatst gebouwd</span><strong class="small-stat"><?=h((string)($metadata['compiled_at']??'Nog niet'))?></strong></article>
</section>

<section class="surface kp-control">
  <div class="section-heading">
    <div>
      <span class="kicker">FASE 1</span>
      <h2>Wiki-categorieën ophalen</h2>
      <p class="muted">De browser gebruikt MediaWiki CORS. Je server-IP hoeft de Wiki daardoor niet rechtstreeks te benaderen.</p>
    </div>
    <div class="actions">
      <button class="btn" type="button" data-kp-all>Alles ophalen</button>
      <button class="btn secondary" type="button" data-kp-compile>Staging publiceren</button>
      <button class="btn danger" type="button" data-kp-clear>Staging wissen</button>
    </div>
  </div>

  <div class="kp-progress" data-kp-progress hidden>
    <div><strong data-kp-title>Voorbereiden</strong><span data-kp-count>0</span></div>
    <div class="batch-progress"><span data-kp-bar></span></div>
    <p data-kp-status>—</p>
  </div>

  <div class="kp-profile-grid">
    <?php foreach($profiles as $profile):?>
      <article class="kp-profile">
        <div>
          <span class="kicker"><?=h($profile['key'])?></span>
          <h3><?=h($profile['label'])?></h3>
          <code><?=h($profile['category'])?></code>
        </div>
        <div>
          <span class="count-pill"><?=number_format((int)($staged_profiles[$profile['key']]??0),0,',','.')?></span>
          <button
            class="btn small"
            type="button"
            data-kp-profile
            data-profile="<?=h($profile['key'])?>"
            data-category="<?=h($profile['category'])?>"
            data-kind="<?=h($profile['kind'])?>"
          >Ophalen</button>
        </div>
      </article>
    <?php endforeach;?>
  </div>
</section>

<section class="surface">
  <div class="section-heading">
    <div><span class="kicker">STAGING</span><h2>Voorbeeld van opgehaalde pagina’s</h2></div>
  </div>
  <div class="tablewrap">
    <table>
      <thead><tr><th>Titel</th><th>Profiel</th><th>Type</th><th>Redirects</th><th>Samenvatting</th></tr></thead>
      <tbody>
      <?php if(!$sample):?><tr><td colspan="5" class="muted">Nog geen Wiki-pagina’s opgehaald.</td></tr><?php endif;?>
      <?php foreach($sample as$page):?>
        <tr>
          <td><strong><?=h($page['title'])?></strong></td>
          <td><?=h($page['profile'])?></td>
          <td><?=h($page['kind'])?></td>
          <td><?=count($page['redirects']??[])?></td>
          <td class="muted"><?=h(mb_strimwidth((string)($page['extract']??''),0,180,'…'))?></td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
</section>

<script>
(() => {
  const api = <?=json_encode((string)($sources['wiki_api']??'https://wiki.guildwars.com/api.php'),JSON_UNESCAPED_SLASHES)?>;
  const panel = document.querySelector('[data-kp-progress]');
  const title = document.querySelector('[data-kp-title]');
  const count = document.querySelector('[data-kp-count]');
  const status = document.querySelector('[data-kp-status]');
  const bar = document.querySelector('[data-kp-bar]');
  let busy = false;

  const show = (heading, text, percent = 0, total = 0) => {
    panel.hidden = false;
    title.textContent = heading;
    status.textContent = text;
    count.textContent = String(total);
    bar.style.width = Math.max(0,Math.min(100,percent)) + '%';
  };

  async function fetchCategory(profile, category, kind) {
    let cmcontinue = '';
    let pages = 0;
    show(profile, `Categorie ${category} wordt gelezen…`, 5, 0);

    do {
      const categoryUrl = new URL(api);
      categoryUrl.searchParams.set('action','query');
      categoryUrl.searchParams.set('list','categorymembers');
      categoryUrl.searchParams.set('cmtitle',category);
      categoryUrl.searchParams.set('cmnamespace','0');
      categoryUrl.searchParams.set('cmlimit','100');
      categoryUrl.searchParams.set('format','json');
      categoryUrl.searchParams.set('origin','*');
      if (cmcontinue) categoryUrl.searchParams.set('cmcontinue',cmcontinue);

      const categoryResponse = await fetch(categoryUrl);
      if (!categoryResponse.ok) throw new Error(`Wiki categorie HTTP ${categoryResponse.status}`);
      const categoryData = await categoryResponse.json();
      const members = categoryData?.query?.categorymembers ?? [];
      const titles = members.map(member => member.title).filter(Boolean);

      if (titles.length) {
        const detailUrl = new URL(api);
        detailUrl.searchParams.set('action','query');
        detailUrl.searchParams.set('titles',titles.join('|'));
        detailUrl.searchParams.set('prop','extracts|info|categories|redirects');
        detailUrl.searchParams.set('exintro','1');
        detailUrl.searchParams.set('explaintext','1');
        detailUrl.searchParams.set('exchars','1000');
        detailUrl.searchParams.set('inprop','url');
        detailUrl.searchParams.set('cllimit','max');
        detailUrl.searchParams.set('rdlimit','max');
        detailUrl.searchParams.set('format','json');
        detailUrl.searchParams.set('origin','*');

        const detailResponse = await fetch(detailUrl);
        if (!detailResponse.ok) throw new Error(`Wiki detail HTTP ${detailResponse.status}`);
        const detailData = await detailResponse.json();
        const batch = Object.values(detailData?.query?.pages ?? {});

        const body = new URLSearchParams({
          profile,
          kind,
          payload: JSON.stringify(batch)
        });
        const saveResponse = await fetch('/knowledge-pack/stage', {
          method:'POST',
          headers:{
            'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept':'application/json'
          },
          body
        });
        const saveData = await saveResponse.json();
        if (!saveResponse.ok || !saveData.ok) throw new Error(saveData.error || 'Staging opslaan mislukt.');
        pages += batch.length;
      }

      cmcontinue = categoryData?.continue?.cmcontinue ?? '';
      show(profile, `${pages} pagina’s opgeslagen…`, cmcontinue ? 55 : 100, pages);
      await new Promise(resolve => setTimeout(resolve, 250));
    } while (cmcontinue);

    show(profile, `${pages} pagina’s uit ${category} opgehaald.`, 100, pages);
    return pages;
  }

  async function runProfile(button) {
    if (busy) return;
    busy = true;
    button.disabled = true;
    try {
      await fetchCategory(button.dataset.profile, button.dataset.category, button.dataset.kind);
    } catch (error) {
      show('Importfout', error.message, 0, 0);
    } finally {
      busy = false;
      button.disabled = false;
    }
  }

  document.querySelectorAll('[data-kp-profile]').forEach(button => {
    button.addEventListener('click', () => runProfile(button));
  });

  document.querySelector('[data-kp-all]')?.addEventListener('click', async event => {
    if (busy) return;
    busy = true;
    event.currentTarget.disabled = true;
    const buttons = [...document.querySelectorAll('[data-kp-profile]')];
    let total = 0;
    try {
      for (let index=0; index<buttons.length; index++) {
        const button = buttons[index];
        total += await fetchCategory(button.dataset.profile, button.dataset.category, button.dataset.kind);
        show('Alle categorieën', `${index+1}/${buttons.length} categorieën verwerkt`, ((index+1)/buttons.length)*100, total);
      }
      show('Import voltooid', `${total} Wiki-pagina’s staan in staging.`, 100, total);
    } catch (error) {
      show('Importfout', error.message, 0, total);
    } finally {
      busy = false;
      event.currentTarget.disabled = false;
    }
  });

  document.querySelector('[data-kp-compile]')?.addEventListener('click', async () => {
    if (busy) return;
    busy = true;
    show('Publiceren', 'Staging wordt gevalideerd en omgezet naar lokale parserdata…', 35, 0);
    try {
      const response = await fetch('/knowledge-pack/compile', {method:'POST',headers:{'Accept':'application/json'}});
      const raw = await response.text();
      let data;
      try {
        data = JSON.parse(raw);
      } catch (_) {
        const cleaned = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        throw new Error(cleaned || `Server gaf geen geldige JSON terug (HTTP ${response.status}).`);
      }
      if (!response.ok || !data.ok) throw new Error(data.error || 'Publiceren mislukt.');
      show('Knowledge Pack gepubliceerd', `${data.items} items en ${data.aliases} aliassen gebouwd.`, 100, data.items);
      setTimeout(() => location.reload(), 1200);
    } catch (error) {
      show('Publicatiefout', error.message, 0, 0);
    } finally {
      busy = false;
    }
  });

  document.querySelector('[data-kp-clear]')?.addEventListener('click', async () => {
    if (!confirm('Alle nog niet gepubliceerde Wiki-staging wissen?')) return;
    const response = await fetch('/knowledge-pack/clear', {method:'POST',headers:{'Accept':'application/json'}});
    const data = await response.json();
    if (data.ok) location.reload();
  });
})();
</script>
