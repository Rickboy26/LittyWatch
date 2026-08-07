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
      <?php
        $sourceType = (string)($profile['source_type'] ?? 'category');
        $sourceLabel = match($sourceType) {
          'list-page' => 'Pagina: ' . (string)($profile['page'] ?? ''),
          'categories' => count($profile['categories'] ?? []) . ' Wiki-categorieën',
          default => (string)($profile['category'] ?? ''),
        };
        $configJson = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      ?>
      <article class="kp-profile">
        <div>
          <span class="kicker"><?=h($profile['key'])?> · <?=h($sourceType)?></span>
          <h3><?=h($profile['label'])?></h3>
          <code><?=h($sourceLabel)?></code>
        </div>
        <div>
          <span class="count-pill"><?=number_format((int)($staged_profiles[$profile['key']]??0),0,',','.')?></span>
          <button
            class="btn small"
            type="button"
            data-kp-profile
            data-config="<?=h((string)$configJson)?>"
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

  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

  async function wikiFetch(url, label, attempts = 3) {
    let lastError;
    for (let attempt = 1; attempt <= attempts; attempt++) {
      try {
        const response = await fetch(url, {headers:{'Accept':'application/json'}, cache:'no-store'});
        if (!response.ok) throw new Error(`${label} HTTP ${response.status}`);
        return await response.json();
      } catch (error) {
        lastError = error;
        if (attempt < attempts) await sleep(700 * attempt);
      }
    }
    throw new Error(`${label}: ${lastError?.message || 'Failed to fetch'} (na ${attempts} pogingen)`);
  }

  async function stagePages(profile, kind, pages) {
    if (!pages.length) return 0;
    const body = new URLSearchParams({profile, kind, payload: JSON.stringify(pages)});
    const response = await fetch('/knowledge-pack/stage', {
      method:'POST',
      headers:{
        'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept':'application/json'
      },
      body
    });
    const raw = await response.text();
    let data;
    try { data = JSON.parse(raw); }
    catch (_) { throw new Error(raw.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim() || 'Staging opslaan gaf geen geldige JSON.'); }
    if (!response.ok || !data.ok) throw new Error(data.error || 'Staging opslaan mislukt.');
    return Number(data.received || pages.length);
  }

  async function fetchCategory(profile, category, kind, progressPrefix = '') {
    let cmcontinue = '';
    let pages = 0;
    let categoryMembers = 0;
    show(profile, `${progressPrefix}${category} wordt gelezen…`, 5, 0);

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

      const categoryData = await wikiFetch(categoryUrl, `Wiki categorie ${category}`);
      const members = categoryData?.query?.categorymembers ?? [];
      const titles = members.map(member => member.title).filter(Boolean);
      categoryMembers += titles.length;

      const DETAIL_BATCH_SIZE = 20;
      for (let start = 0; start < titles.length; start += DETAIL_BATCH_SIZE) {
        const titleBatch = titles.slice(start, start + DETAIL_BATCH_SIZE);
        const detailUrl = new URL(api);
        detailUrl.searchParams.set('action','query');
        detailUrl.searchParams.set('titles',titleBatch.join('|'));
        detailUrl.searchParams.set('prop','extracts|info|categories|redirects');
        detailUrl.searchParams.set('exintro','1');
        detailUrl.searchParams.set('explaintext','1');
        detailUrl.searchParams.set('exchars','1000');
        detailUrl.searchParams.set('inprop','url');
        detailUrl.searchParams.set('cllimit','max');
        detailUrl.searchParams.set('rdlimit','max');
        detailUrl.searchParams.set('format','json');
        detailUrl.searchParams.set('origin','*');

        const detailData = await wikiFetch(detailUrl, `Wiki details ${category}`);
        const batch = Object.values(detailData?.query?.pages ?? {}).filter(page => !page?.missing);
        pages += await stagePages(profile, kind, batch);
        show(profile, `${progressPrefix}${pages} pagina’s opgeslagen…`, 55, pages);
        await sleep(120);
      }

      cmcontinue = categoryData?.continue?.cmcontinue ?? '';
      await sleep(180);
    } while (cmcontinue);

    if (categoryMembers === 0) {
      throw new Error(`${category} leverde 0 pagina’s op. Controleer of deze categorie op Guild Wars Wiki bestaat.`);
    }
    return pages;
  }

  function cleanListName(value) {
    return String(value || '')
      .replace(/\[[^\]]*\]/g, '')
      .replace(/[†‡*]+$/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function regexMatches(value, pattern) {
    if (!pattern) return true;
    try { return new RegExp(pattern, 'iu').test(value); }
    catch (_) { return true; }
  }

  async function fetchListPage(config) {
    const profile = config.key;
    const kind = config.kind;
    const pageName = config.page;
    show(profile, `Wiki-pagina ${pageName} wordt gelezen…`, 10, 0);

    const pageUrl = new URL(`https://wiki.guildwars.com/wiki/${encodeURIComponent(pageName.replace(/ /g,'_'))}`);
    let rows = [];

    if (config.extractor === 'configured-names') {
      rows = (config.names || []).map(name => ({
        title: name,
        pageid: 0,
        fullurl: pageUrl.toString(),
        extract: `Afgeleid van Guild Wars Wiki-pagina ${pageName}.`,
        categories: [],
        redirects: []
      }));
    } else {
      const parseUrl = new URL(api);
      parseUrl.searchParams.set('action','parse');
      parseUrl.searchParams.set('page',pageName);
      parseUrl.searchParams.set('prop','text');
      parseUrl.searchParams.set('format','json');
      parseUrl.searchParams.set('origin','*');
      const data = await wikiFetch(parseUrl, `Wiki lijstpagina ${pageName}`);
      const html = data?.parse?.text?.['*'];
      if (!html) throw new Error(`Wiki-pagina ${pageName} bevatte geen parseerbare HTML.`);

      const doc = new DOMParser().parseFromString(html, 'text/html');
      const seen = new Set();
      const tables = Array.from(doc.querySelectorAll('table'));

      for (const table of tables) {
        // MediaWiki does not guarantee the `wikitable` class in action=parse output.
        // Determine the item-name column from the table header instead of assuming
        // that the first td is always the name (Inscription tables start with Icon).
        let nameColumn = 0;
        const headerRow = Array.from(table.querySelectorAll('tr')).find(tr => tr.querySelector('th'));
        if (headerRow) {
          const headers = Array.from(headerRow.querySelectorAll('th,td')).map(cell => cleanListName(cell.textContent));
          const explicitNameColumn = headers.findIndex(header => /^(?:name|item|item name|consumable|sweet|alcohol)$/iu.test(header));
          if (explicitNameColumn >= 0) nameColumn = explicitNameColumn;
        }

        for (const tr of table.querySelectorAll('tr')) {
          const cells = Array.from(tr.querySelectorAll(':scope > td'));
          if (!cells.length || nameColumn >= cells.length) continue;

          let name = cleanListName(cells[nameColumn].textContent);
          if (!name || name.length > 100) continue;
          if (/^(?:name|icon|description|enhancement|condition\s*\/?\s*cost)$/iu.test(name)) continue;
          if (config.exclude_regex && regexMatches(name, config.exclude_regex)) continue;
          if (config.include_regex && !regexMatches(name, config.include_regex)) continue;

          const key = name.toLocaleLowerCase();
          if (seen.has(key)) continue;
          seen.add(key);
          rows.push({
            title: name,
            pageid: 0,
            fullurl: pageUrl.toString(),
            extract: cleanListName(tr.textContent).slice(0, 1000),
            categories: [],
            redirects: []
          });
        }
      }
    }

    if (!rows.length) throw new Error(`${pageName} leverde 0 bruikbare lijstregels op.`);

    const BATCH_SIZE = 40;
    let saved = 0;
    for (let i = 0; i < rows.length; i += BATCH_SIZE) {
      saved += await stagePages(profile, kind, rows.slice(i, i + BATCH_SIZE));
      show(profile, `${saved}/${rows.length} lijstitems opgeslagen…`, 30 + (saved / rows.length) * 70, saved);
      await sleep(100);
    }
    show(profile, `${saved} items uit ${pageName} opgehaald.`, 100, saved);
    return saved;
  }

  async function fetchProfile(config) {
    const type = config.source_type || 'category';
    if (type === 'list-page') return fetchListPage(config);
    if (type === 'list-pages') {
      const pages = config.pages || [];
      let total = 0;
      for (let i = 0; i < pages.length; i++) {
        const pageConfig = {...config, ...pages[i], source_type:'list-page'};
        total += await fetchListPage(pageConfig);
        show(config.key, `${i+1}/${pages.length} lijstpagina’s verwerkt · ${total} items`, ((i+1)/pages.length)*100, total);
      }
      if (!pages.length) throw new Error(`${config.label} heeft geen Wiki-lijstpagina’s ingesteld.`);
      show(config.key, `${total} items uit ${pages.length} lijstpagina’s opgehaald.`, 100, total);
      return total;
    }
    if (type === 'categories') {
      const categories = config.categories || [];
      let total = 0;
      for (let i = 0; i < categories.length; i++) {
        total += await fetchCategory(config.key, categories[i], config.kind, `${i+1}/${categories.length} · `);
        show(config.key, `${i+1}/${categories.length} wapencategorieën verwerkt · ${total} pagina’s`, ((i+1)/categories.length)*100, total);
      }
      if (!categories.length) throw new Error(`${config.label} heeft geen broncategorieën ingesteld.`);
      show(config.key, `${total} pagina’s uit ${categories.length} categorieën opgehaald.`, 100, total);
      return total;
    }
    return fetchCategory(config.key, config.category, config.kind);
  }

  function configFromButton(button) {
    try { return JSON.parse(button.dataset.config || '{}'); }
    catch (_) { throw new Error('Ongeldige bronconfiguratie in sources.json.'); }
  }

  async function runProfile(button) {
    if (busy) return;
    busy = true;
    button.disabled = true;
    try { await fetchProfile(configFromButton(button)); }
    catch (error) { show('Importfout', error.message, 0, 0); }
    finally { busy = false; button.disabled = false; }
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
        const config = configFromButton(buttons[index]);
        total += await fetchProfile(config);
        show('Alle bronnen', `${index+1}/${buttons.length} bronnen verwerkt`, ((index+1)/buttons.length)*100, total);
      }
      show('Import voltooid', `${total} Wiki-items/pagina’s staan in staging.`, 100, total);
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
      try { data = JSON.parse(raw); }
      catch (_) {
        const cleaned = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        throw new Error(cleaned || `Server gaf geen geldige JSON terug (HTTP ${response.status}).`);
      }
      if (!response.ok || !data.ok) throw new Error(data.error || 'Publiceren mislukt.');
      show('Knowledge Pack gepubliceerd', `${data.items} items en ${data.aliases} aliassen gebouwd.`, 100, data.items);
      setTimeout(() => location.reload(), 1200);
    } catch (error) {
      show('Publicatiefout', error.message, 0, 0);
    } finally { busy = false; }
  });

  document.querySelector('[data-kp-clear]')?.addEventListener('click', async () => {
    if (!confirm('Alle nog niet gepubliceerde Wiki-staging wissen?')) return;
    const response = await fetch('/knowledge-pack/clear', {method:'POST',headers:{'Accept':'application/json'}});
    const data = await response.json();
    if (data.ok) location.reload();
  });
})();
</script>
