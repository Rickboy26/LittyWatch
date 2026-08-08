(() => {
  'use strict';

  const root = document.querySelector('[data-inventory-auto-map]');
  const dataNode = document.getElementById('inventory-auto-items');
  if (!root || !dataNode) return;

  let items = [];
  try { items = JSON.parse(dataNode.textContent || '[]'); }
  catch (_) { items = []; }

  const startButton = root.querySelector('[data-auto-map-start]');
  const stopButton = root.querySelector('[data-auto-map-stop]');
  const status = root.querySelector('[data-auto-map-status]');
  const detail = root.querySelector('[data-auto-map-detail]');
  const progress = root.querySelector('[data-auto-map-progress]');
  const matchedNode = root.querySelector('[data-auto-map-matched]');
  const unresolvedNode = root.querySelector('[data-auto-map-unresolved]');
  const failedNode = root.querySelector('[data-auto-map-failed]');
  const processedNode = root.querySelector('[data-auto-map-processed]');

  const api = 'https://wiki.guildwars.com/api.php';
  const fingerprintsUrl = '/assets/game-items/inventory-fingerprints.json?v=3m7';
  const batchSize = 18;
  const saveBatchSize = 40;
  let stopRequested = false;
  let running = false;
  const dnsCache = new Map();

  async function resolveHost(host) {
    host = String(host || '').trim().toLowerCase();
    if (!host) return '';
    if (dnsCache.has(host)) return dnsCache.get(host);
    const providers = [
      `https://cloudflare-dns.com/dns-query?name=${encodeURIComponent(host)}&type=A`,
      `https://dns.google/resolve?name=${encodeURIComponent(host)}&type=A`,
    ];
    for (const url of providers) {
      try {
        const response = await fetch(url, {headers:{'Accept':'application/dns-json'}, cache:'no-store', mode:'cors'});
        if (!response.ok) continue;
        const data = await response.json();
        const answers = Array.isArray(data?.Answer) ? data.Answer : [];
        const hit = answers.find(row => Number(row?.type) === 1 && /^\d{1,3}(?:\.\d{1,3}){3}$/.test(String(row?.data || '')));
        if (hit) {
          const ip = String(hit.data);
          dnsCache.set(host, ip);
          return ip;
        }
      } catch (_) {}
    }
    dnsCache.set(host, '');
    return '';
  }

  const normalizeTitle = value => String(value || '')
    .replace(/_/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .toLocaleLowerCase();

  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

  function setProgress(processed, total, matched, unresolved, failed, message) {
    const pct = total > 0 ? Math.round((processed / total) * 100) : 0;
    if (progress) progress.style.width = `${Math.max(0, Math.min(100, pct))}%`;
    if (processedNode) processedNode.textContent = `${processed}/${total}`;
    if (matchedNode) matchedNode.textContent = String(matched);
    if (unresolvedNode) unresolvedNode.textContent = String(unresolved);
    if (failedNode) failedNode.textContent = String(failed);
    if (status && message) status.textContent = message;
  }

  async function fetchJson(url, label, attempts = 3) {
    let lastError;
    for (let attempt = 1; attempt <= attempts; attempt++) {
      try {
        const response = await fetch(url, {headers: {'Accept':'application/json'}, cache:'no-store'});
        if (!response.ok) throw new Error(`${label}: HTTP ${response.status}`);
        return await response.json();
      } catch (error) {
        lastError = error;
        if (attempt < attempts) await sleep(500 * attempt);
      }
    }
    throw lastError instanceof Error ? lastError : new Error(`${label} mislukt.`);
  }

  function sha1Base36ToHex(value) {
    const text = String(value || '').trim().toLowerCase();
    if (!text) return '';
    if (/^[0-9a-f]{40}$/.test(text)) return text;
    try {
      let n = 0n;
      for (const ch of text) {
        const code = ch.charCodeAt(0);
        let digit = -1;
        if (code >= 48 && code <= 57) digit = code - 48;
        else if (code >= 97 && code <= 122) digit = code - 87;
        if (digit < 0 || digit >= 36) return '';
        n = n * 36n + BigInt(digit);
      }
      return n.toString(16).padStart(40, '0');
    } catch (_) {
      return '';
    }
  }

  function popcnt32(value) {
    value >>>= 0;
    value -= (value >>> 1) & 0x55555555;
    value = (value & 0x33333333) + ((value >>> 2) & 0x33333333);
    return ((((value + (value >>> 4)) & 0x0F0F0F0F) * 0x01010101) >>> 24);
  }

  function makeHash(values, width, height, accessor) {
    let hi = 0, lo = 0, bitIndex = 0;
    for (let y = 0; y < height; y++) {
      for (let x = 0; x < width - 1; x++) {
        const left = accessor(values, x, y, width);
        const right = accessor(values, x + 1, y, width);
        const bit = left > right ? 1 : 0;
        if (bitIndex < 32) hi = ((hi << 1) | bit) >>> 0;
        else lo = ((lo << 1) | bit) >>> 0;
        bitIndex++;
      }
    }
    return [hi >>> 0, lo >>> 0];
  }

  async function imageFingerprint(candidate) {
    const sourceTitle = String(candidate?.sourceTitle || '').trim();
    const remoteUrl = String(candidate?.url || '').trim();
    const urls = [];
    if (sourceTitle) {
      let ip = '';
      let remoteHost = '';
      if (remoteUrl) {
        try { remoteHost = new URL(remoteUrl).hostname; } catch (_) {}
      }
      if (remoteHost) ip = await resolveHost(remoteHost);
      const q = new URLSearchParams({file:sourceTitle});
      if (remoteUrl) q.set('url', remoteUrl);
      if (ip) q.set('ip', ip);
      urls.push(`/game-assets/wiki-icon?${q.toString()}`);
    }
    if (remoteUrl) urls.push(remoteUrl);
    let blob = null;
    let lastError = null;
    let via = '';
    for (const url of urls) {
      try {
        const response = await fetch(url, {mode:'cors', cache:'no-store', headers:{'Accept':'image/png,image/*,application/json'}});
        if (!response.ok) {
          let detail = '';
          try {
            const text = await response.text();
            const data = JSON.parse(text);
            const attempts = Array.isArray(data?.attempts) ? data.attempts : [];
            detail = attempts.map(a => `${a.method || '?'} status=${a.status ?? '-'} errno=${a.errno ?? '-'} bytes=${a.bytes ?? '-'} error=${a.error || ''}`).join(' / ');
          } catch (_) {}
          throw new Error(`Afbeelding HTTP ${response.status}${detail ? ` · ${detail}` : ''}`);
        }
        const type = String(response.headers.get('content-type') || '').toLowerCase();
        const candidateBlob = await response.blob();
        if (!type.includes('image') && candidateBlob.type && !candidateBlob.type.includes('image')) throw new Error('Geen afbeelding ontvangen.');
        blob = candidateBlob;
        if (url.startsWith('/game-assets/wiki-icon')) {
          const source = String(response.headers.get('x-littywatch-icon-source') || 'proxy');
          via = source === 'resolved-ip' ? 'proxy-resolved' : 'proxy';
        } else via = 'direct';
        break;
      } catch (error) { lastError = error; }
    }
    if (!blob) throw (lastError instanceof Error ? lastError : new Error('Wiki-afbeelding kon niet worden gelezen.'));
    let bitmap;
    if ('createImageBitmap' in window) {
      bitmap = await createImageBitmap(blob);
    } else {
      bitmap = await new Promise((resolve, reject) => {
        const objectUrl = URL.createObjectURL(blob);
        const img = new Image();
        img.onload = () => { URL.revokeObjectURL(objectUrl); resolve(img); };
        img.onerror = () => { URL.revokeObjectURL(objectUrl); reject(new Error('Afbeelding kon niet worden gelezen.')); };
        img.src = objectUrl;
      });
    }

    const source = document.createElement('canvas');
    source.width = bitmap.width;
    source.height = bitmap.height;
    const sctx = source.getContext('2d', {willReadFrequently:true});
    sctx.clearRect(0, 0, source.width, source.height);
    sctx.drawImage(bitmap, 0, 0);
    const pixels = sctx.getImageData(0, 0, source.width, source.height).data;
    if (typeof bitmap.close === 'function') bitmap.close();

    let minX = source.width, minY = source.height, maxX = -1, maxY = -1;
    let weightedR = 0, weightedG = 0, weightedB = 0, alphaSum = 0;
    for (let y = 0; y < source.height; y++) {
      for (let x = 0; x < source.width; x++) {
        const i = (y * source.width + x) * 4;
        const a = pixels[i + 3];
        if (a > 16) {
          if (x < minX) minX = x;
          if (x > maxX) maxX = x;
          if (y < minY) minY = y;
          if (y > maxY) maxY = y;
        }
        if (a > 0) {
          weightedR += pixels[i] * a;
          weightedG += pixels[i + 1] * a;
          weightedB += pixels[i + 2] * a;
          alphaSum += a;
        }
      }
    }
    if (maxX < minX || maxY < minY) throw new Error('Leeg icoon.');

    const cropW = maxX - minX + 1;
    const cropH = maxY - minY + 1;
    const luma = [];
    const alpha = [];
    // Deterministic nearest-neighbour sampling. This deliberately avoids
    // browser/PIL interpolation differences in the local fingerprint index.
    for (let gy = 0; gy < 8; gy++) {
      const sy = minY + Math.min(cropH - 1, Math.floor((gy + 0.5) * cropH / 8));
      for (let gx = 0; gx < 9; gx++) {
        const sx = minX + Math.min(cropW - 1, Math.floor((gx + 0.5) * cropW / 9));
        const i = (sy * source.width + sx) * 4;
        const a = pixels[i + 3];
        const lum = (pixels[i] * 0.299 + pixels[i + 1] * 0.587 + pixels[i + 2] * 0.114) * (a / 255);
        luma.push(lum);
        alpha.push(a);
      }
    }
    const valueAt = (values, x, y, width) => values[y * width + x];
    const [lumaHi, lumaLo] = makeHash(luma, 9, 8, valueAt);
    const [alphaHi, alphaLo] = makeHash(alpha, 9, 8, valueAt);

    return {
      via,
      lumaHi, lumaLo, alphaHi, alphaLo,
      r: alphaSum ? Math.round(weightedR / alphaSum) : 0,
      g: alphaSum ? Math.round(weightedG / alphaSum) : 0,
      b: alphaSum ? Math.round(weightedB / alphaSum) : 0,
      ratio: cropH > 0 ? cropW / cropH : 1,
    };
  }

  function visualMatch(fp, localRows) {
    let best = null;
    let secondDifferent = null;
    for (const row of localRows) {
      const ratio = Number(row[8] || 1);
      const ratioDiff = Math.abs(Math.log(Math.max(0.05, fp.ratio) / Math.max(0.05, ratio)));
      if (ratioDiff > 0.34) continue;

      const dr = fp.r - Number(row[5] || 0);
      const dg = fp.g - Number(row[6] || 0);
      const db = fp.b - Number(row[7] || 0);
      const color = Math.sqrt(dr * dr + dg * dg + db * db);
      if (color > 115) continue;

      const dLum = popcnt32((fp.lumaHi ^ Number(row[1])) >>> 0) + popcnt32((fp.lumaLo ^ Number(row[2])) >>> 0);
      const dAlpha = popcnt32((fp.alphaHi ^ Number(row[3])) >>> 0) + popcnt32((fp.alphaLo ^ Number(row[4])) >>> 0);
      const score = dLum + dAlpha * 1.35 + color / 18 + ratioDiff * 10;
      const candidate = {dat:Number(row[0]), score, signature:`${row[1]}:${row[2]}:${row[3]}:${row[4]}:${row[5]}:${row[6]}:${row[7]}:${row[8]}`};
      if (!best || candidate.score < best.score) {
        if (best && best.signature !== candidate.signature) secondDifferent = best;
        best = candidate;
      } else if (candidate.signature !== best.signature && (!secondDifferent || candidate.score < secondDifferent.score)) {
        secondDifferent = candidate;
      }
    }

    if (!best) return null;
    const gap = secondDifferent ? secondDifferent.score - best.score : 99;
    // Exact/direct filename is already a strong semantic hint, so the visual
    // threshold can be strict without requiring a giant nearest-neighbour gap.
    if (best.score > 26 || gap < 1.2) return null;
    const confidence = Math.max(0.905, Math.min(0.995, 0.995 - best.score * 0.0035 + Math.min(gap, 12) * 0.0018));
    return {dat:best.dat, confidence, score:best.score, gap};
  }

  function buildLocalIndexes(fingerprintData) {
    const rows = Array.isArray(fingerprintData?.icons) ? fingerprintData.icons : [];
    const sha1 = new Map();
    for (const row of rows) {
      const hex = String(row[9] || '').toLowerCase();
      if (!hex) continue;
      if (!sha1.has(hex)) sha1.set(hex, []);
      sha1.get(hex).push(Number(row[0]));
    }
    return {rows, sha1};
  }

  function candidatesForItem(item) {
    const base = String(item.wiki_title || item.item || '').trim().replace(/^File:/i, '').replace(/\.png$/i, '');
    if (!base) return [];
    const titles = [`File:${base}.png`, `File:${base} icon.png`];
    return [...new Set(titles)];
  }

  async function queryWikiFileBatch(batch) {
    const candidateMap = new Map();
    const titles = [];
    for (const item of batch) {
      for (const title of candidatesForItem(item)) {
        const key = normalizeTitle(title);
        if (!candidateMap.has(key)) {
          candidateMap.set(key, item);
          titles.push(title);
        }
      }
    }
    if (!titles.length) return [];

    const url = new URL(api);
    url.searchParams.set('action', 'query');
    url.searchParams.set('prop', 'imageinfo');
    url.searchParams.set('iiprop', 'url|sha1|size');
    url.searchParams.set('iiurlwidth', '64');
    url.searchParams.set('redirects', '1');
    url.searchParams.set('format', 'json');
    url.searchParams.set('formatversion', '2');
    url.searchParams.set('origin', '*');
    url.searchParams.set('titles', titles.join('|'));

    const data = await fetchJson(url, 'Guild Wars Wiki');
    const redirects = new Map();
    for (const redir of data?.query?.redirects || []) {
      redirects.set(normalizeTitle(redir.to), normalizeTitle(redir.from));
    }

    const found = [];
    for (const page of data?.query?.pages || []) {
      if (!page || page.missing || !Array.isArray(page.imageinfo) || !page.imageinfo[0]) continue;
      const pageKey = normalizeTitle(page.title);
      const sourceKey = redirects.get(pageKey) || pageKey;
      const item = candidateMap.get(sourceKey) || candidateMap.get(pageKey);
      if (!item) continue;
      const info = page.imageinfo[0];
      const width = Number(info.width || info.thumbwidth || 0);
      const height = Number(info.height || info.thumbheight || 0);
      // Inventory icons are small square assets. Reject page artwork/photos.
      if (width && height && (width > 160 || height > 160 || width < 24 || height < 24)) continue;
      found.push({
        item,
        sourceTitle:String(page.title || ''),
        sha1:String(info.sha1 || ''),
        url:String(info.thumburl || info.url || ''),
      });
    }
    return found;
  }

  async function saveMatches(matches) {
    if (!matches.length) return {accepted:0, skipped:0, missing:0};
    const body = new URLSearchParams({payload:JSON.stringify(matches)});
    const response = await fetch('/game-assets/auto-link', {
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
    catch (_) { throw new Error(raw.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim() || 'Server gaf geen geldige JSON.'); }
    if (!response.ok || !data.ok) throw new Error(data.error || 'Koppelingen opslaan mislukt.');
    return data;
  }

  async function run() {
    if (running || !items.length) return;
    running = true;
    stopRequested = false;
    startButton.disabled = true;
    stopButton.hidden = false;
    if (detail) detail.textContent = 'Lokale fingerprintindex wordt geladen…';

    let processed = 0, matched = 0, unresolved = 0, failed = 0;
    let wikiCandidates = 0, imageReads = 0, proxyReads = 0, resolvedProxyReads = 0, directReads = 0, imageReadFailures = 0;
    const readErrorSamples = [];
    const total = items.length;
    const pendingSave = [];
    setProgress(0, total, 0, 0, 0, 'Mapper starten…');

    try {
      const fingerprintData = await fetchJson(fingerprintsUrl, 'Lokale icon-index');
      const local = buildLocalIndexes(fingerprintData);
      if (!local.rows.length) throw new Error('Lokale icon-fingerprints ontbreken. Indexeer/deploy de inventory assets opnieuw.');

      for (let offset = 0; offset < items.length; offset += batchSize) {
        if (stopRequested) break;
        const batch = items.slice(offset, offset + batchSize);
        if (detail) detail.textContent = `Wiki bestandsnamen controleren · ${Math.min(offset + batch.length, total)} van ${total}`;
        let pages;
        try {
          pages = await queryWikiFileBatch(batch);
        } catch (error) {
          failed += batch.length;
          processed += batch.length;
          setProgress(processed, total, matched, unresolved, failed, `Wiki-batch overgeslagen: ${error?.message || 'onbekende fout'}`);
          await sleep(400);
          continue;
        }

        wikiCandidates += pages.length;
        const pagesByKey = new Map();
        for (const candidate of pages) {
          const key = String(candidate.item.item_key || '');
          if (!pagesByKey.has(key)) pagesByKey.set(key, []);
          pagesByKey.get(key).push(candidate);
        }

        for (const item of batch) {
          if (stopRequested) break;
          const key = String(item.item_key || '');
          const candidates = pagesByKey.get(key) || [];
          let found = null;

          // Fast path: exact binary SHA1. No Wiki image needs to be downloaded.
          for (const candidate of candidates) {
            const hex = sha1Base36ToHex(candidate.sha1);
            const ids = hex ? local.sha1.get(hex) : null;
            if (ids && ids.length) {
              found = {
                item_key:key,
                dat_file_id:ids[0],
                confidence:0.999,
                source_title:candidate.sourceTitle,
              };
              break;
            }
          }

          // Safe visual fallback: only a direct File:<item>.png candidate and a
          // strict local fingerprint match can be stored automatically.
          if (!found) {
            for (const candidate of candidates) {
              if (!candidate.url) continue;
              try {
                const fp = await imageFingerprint(candidate);
                imageReads++;
                if (fp.via === 'proxy-resolved') { proxyReads++; resolvedProxyReads++; }
                else if (fp.via === 'proxy') proxyReads++;
                else if (fp.via === 'direct') directReads++;
                const visual = visualMatch(fp, local.rows);
                if (visual) {
                  found = {
                    item_key:key,
                    dat_file_id:visual.dat,
                    confidence:Number(visual.confidence.toFixed(3)),
                    source_title:candidate.sourceTitle,
                  };
                  break;
                }
              } catch (error) {
                imageReadFailures++;
                if (readErrorSamples.length < 3) {
                  const message = String(error?.message || error || 'onbekende leesfout');
                  readErrorSamples.push(`${candidate.sourceTitle || 'Wiki-icon'}: ${message}`);
                }
                // One unreadable Wiki image must not stop the complete run.
              }
            }
          }

          if (found) {
            pendingSave.push(found);
            matched++;
          } else {
            unresolved++;
          }
          processed++;

          if (pendingSave.length >= saveBatchSize) {
            const chunk = pendingSave.splice(0, pendingSave.length);
            try { await saveMatches(chunk); }
            catch (error) {
              matched -= chunk.length;
              failed += chunk.length;
              if (detail) detail.textContent = `Opslaan mislukt: ${error?.message || 'onbekende fout'}`;
            }
          }
          setProgress(processed, total, matched, unresolved, failed, stopRequested ? 'Stoppen…' : 'Inventory icons herkennen…');
        }
        await sleep(100);
      }

      if (pendingSave.length) {
        const chunk = pendingSave.splice(0, pendingSave.length);
        try { await saveMatches(chunk); }
        catch (error) {
          matched -= chunk.length;
          failed += chunk.length;
          if (detail) detail.textContent = `Laatste koppelingen konden niet worden opgeslagen: ${error?.message || 'onbekende fout'}`;
        }
      }

      if (stopRequested) {
        if (status) status.textContent = 'Automatische herkenning gestopt.';
        if (detail) detail.textContent = 'Alles wat al met hoge zekerheid was gevonden, is opgeslagen. Je kunt later verdergaan.';
      } else {
        if (status) status.textContent = 'Automatische herkenning afgerond.';
        if (detail) {
          const samples = readErrorSamples.length ? ` Eerste leesfout: ${readErrorSamples[0]}` : '';
          detail.textContent = `${matched} nieuwe koppelingen · ${wikiCandidates} Wiki-bestanden gevonden · ${imageReads} afbeeldingen gelezen (${proxyReads} via LittyWatch, waarvan ${resolvedProxyReads} via DNS-bypass; ${directReads} direct) · ${imageReadFailures} leesfouten · ${unresolved} niet zeker genoeg.${samples}`;
        }
        if (matched > 0) {
          const reload = document.createElement('button');
          reload.className = 'btn secondary';
          reload.type = 'button';
          reload.textContent = 'Resultaat verversen';
          reload.addEventListener('click', () => location.reload());
          root.querySelector('.asset-auto-actions')?.appendChild(reload);
        }
      }
    } catch (error) {
      if (status) status.textContent = 'Automatische herkenning kon niet starten.';
      if (detail) detail.textContent = error?.message || 'Onbekende fout.';
    } finally {
      running = false;
      startButton.disabled = false;
      stopButton.hidden = true;
    }
  }

  startButton?.addEventListener('click', run);
  stopButton?.addEventListener('click', () => {
    stopRequested = true;
    stopButton.disabled = true;
    setTimeout(() => { stopButton.disabled = false; }, 1200);
  });
})();
