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

  const gwMarketRaw = 'https://raw.githubusercontent.com/Eta92/GW-Market/main/assets/items';
  const fingerprintsUrl = '/assets/game-items/inventory-fingerprints.json?v=3n';
  const batchSize = 12;
  const saveBatchSize = 40;
  let stopRequested = false;
  let running = false;

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
    const remoteUrl = String(candidate?.url || '').trim();
    if (!remoteUrl) throw new Error('Geen referentie-afbeelding.');
    const response = await fetch(remoteUrl, {mode:'cors',cache:'force-cache',headers:{'Accept':'image/png,image/*'}});
    if (!response.ok) throw new Error(`GW Market afbeelding HTTP ${response.status}`);
    const blob = await response.blob();
    if (!blob.size) throw new Error('Lege referentie-afbeelding.');

    let bitmap;
    if ('createImageBitmap' in window) bitmap = await createImageBitmap(blob);
    else bitmap = await new Promise((resolve,reject)=>{
      const objectUrl=URL.createObjectURL(blob), img=new Image();
      img.onload=()=>{URL.revokeObjectURL(objectUrl);resolve(img);};
      img.onerror=()=>{URL.revokeObjectURL(objectUrl);reject(new Error('Afbeelding kon niet worden gelezen.'));};
      img.src=objectUrl;
    });

    const source=document.createElement('canvas');
    source.width=bitmap.width; source.height=bitmap.height;
    const sctx=source.getContext('2d',{willReadFrequently:true});
    sctx.clearRect(0,0,source.width,source.height); sctx.drawImage(bitmap,0,0);
    const pixels=sctx.getImageData(0,0,source.width,source.height).data;
    if(typeof bitmap.close==='function')bitmap.close();

    let minX=source.width,minY=source.height,maxX=-1,maxY=-1;
    let weightedR=0,weightedG=0,weightedB=0,alphaSum=0;
    for(let y=0;y<source.height;y++)for(let x=0;x<source.width;x++){
      const i=(y*source.width+x)*4,a=pixels[i+3];
      if(a>16){if(x<minX)minX=x;if(x>maxX)maxX=x;if(y<minY)minY=y;if(y>maxY)maxY=y;}
      if(a>0){weightedR+=pixels[i]*a;weightedG+=pixels[i+1]*a;weightedB+=pixels[i+2]*a;alphaSum+=a;}
    }
    if(maxX<minX||maxY<minY)throw new Error('Leeg icoon.');
    const cropW=maxX-minX+1,cropH=maxY-minY+1,luma=[],alpha=[];
    for(let gy=0;gy<8;gy++){
      const sy=minY+Math.min(cropH-1,Math.floor((gy+.5)*cropH/8));
      for(let gx=0;gx<9;gx++){
        const sx=minX+Math.min(cropW-1,Math.floor((gx+.5)*cropW/9)),i=(sy*source.width+sx)*4,a=pixels[i+3];
        luma.push((pixels[i]*.299+pixels[i+1]*.587+pixels[i+2]*.114)*(a/255)); alpha.push(a);
      }
    }
    const valueAt=(values,x,y,width)=>values[y*width+x];
    const [lumaHi,lumaLo]=makeHash(luma,9,8,valueAt),[alphaHi,alphaLo]=makeHash(alpha,9,8,valueAt);
    return {via:'gwmarket',lumaHi,lumaLo,alphaHi,alphaLo,
      r:alphaSum?Math.round(weightedR/alphaSum):0,g:alphaSum?Math.round(weightedG/alphaSum):0,b:alphaSum?Math.round(weightedB/alphaSum):0,
      ratio:cropH>0?cropW/cropH:1};
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

  function gwMarketFilename(item) {
    let name=String(item.wiki_title||item.item||'').trim().replace(/^File:/i,'').replace(/\.png$/i,'');
    return name ? name.replace(/ /g,'_')+'.png' : '';
  }

  function categoryCandidates(item) {
    const n=String(item.item||'').toLowerCase(), out=[];
    const push=v=>{if(!out.includes(v))out.push(v);};
    if(/ecto|zaishen key|armbrace|platin|black dye/.test(n))push('currency');
    if(/miniature|mini\b/.test(n))push('miniature');
    if(/rune|insignia/.test(n))push('rune');
    if(/tome/.test(n))push('tome');
    if(/inscription|grip|pommel|hilt|haft|handle|string|staff head|wrapping|focus core|shield handle/.test(n))push('upgrade');
    if(/wood|cloth|scale|ingot|dust|granite|bone|hide|leather|fiber|plank|gemstone|amber|jade|ectoplasm|obsidian/.test(n))push('material');
    if(/alcohol|ale|beer|cupcake|sweet|rock candy|conset|essence|grail|armor of salvation|summoning stone|tonic/.test(n))push('consumable');
    if(/sword|axe|bow|dagger|spear|scythe|hammer|maul|staff|wand|rod|focus|shield|scepter|blade|recurve|flatbow|longbow|shortbow/.test(n)){push('weapon');push('unique');}
    ['special','unique','weapon','consumable','material','miniature','rune','tome','upgrade','currency'].forEach(push);
    return out;
  }

  async function findGwMarketCandidate(item) {
    const filename=gwMarketFilename(item);
    if(!filename)return null;
    for(const category of categoryCandidates(item)){
      const url=`${gwMarketRaw}/${category}/${encodeURIComponent(filename).replace(/%2F/gi,'/')}`;
      try{
        const response=await fetch(url,{method:'GET',mode:'cors',cache:'force-cache',headers:{'Accept':'image/png,image/*'}});
        if(!response.ok)continue;
        const blob=await response.blob();
        if(!blob.size||!String(blob.type||'').includes('image'))continue;
        return {item,sourceTitle:`GW-Market/${category}/${filename}`,url:URL.createObjectURL(blob),revoke:true};
      }catch(_){}
    }
    return null;
  }

  async function queryGwMarketBatch(batch) {
    const found=[];
    for(let i=0;i<batch.length;i+=4){
      const rows=await Promise.all(batch.slice(i,i+4).map(findGwMarketCandidate));
      for(const row of rows)if(row)found.push(row);
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
    let sourceCandidates = 0, imageReads = 0, imageReadFailures = 0;
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
        if (detail) detail.textContent = `GW Market inventory assets controleren · ${Math.min(offset + batch.length, total)} van ${total}`;
        let pages;
        try {
          pages = await queryGwMarketBatch(batch);
        } catch (error) {
          failed += batch.length;
          processed += batch.length;
          setProgress(processed, total, matched, unresolved, failed, `GW Market-batch overgeslagen: ${error?.message || 'onbekende fout'}`);
          await sleep(400);
          continue;
        }

        sourceCandidates += pages.length;
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

          // Visuele match tegen de lokale Gw.dat bibliotheek: only a direct File:<item>.png candidate and a
          // strict local fingerprint match can be stored automatically.
          if (!found) {
            for (const candidate of candidates) {
              if (!candidate.url) continue;
              try {
                const fp = await imageFingerprint(candidate);
                imageReads++;
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
                  readErrorSamples.push(`${candidate.sourceTitle || 'GW Market-icon'}: ${message}`);
                }
                // One unreadable reference image must not stop the complete run.
              } finally {
                if(candidate.revoke&&candidate.url)URL.revokeObjectURL(candidate.url);
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
          detail.textContent = `${matched} nieuwe koppelingen · ${sourceCandidates} GW Market inventory assets gevonden · ${imageReads} afbeeldingen vergeleken · ${imageReadFailures} leesfouten · ${unresolved} niet zeker genoeg.${samples}`;
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
