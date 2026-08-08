(()=>{
const total=document.querySelector('[data-cov-total]'),linked=document.querySelector('[data-cov-linked]'),missing=document.querySelector('[data-cov-missing]');
const show=document.querySelector('[data-show-missing]'),wrap=document.querySelector('[data-missing-wrap]'),list=document.querySelector('[data-missing-list]'),search=document.querySelector('[data-missing-search]');
if(!total)return;
let rows=[];
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
function render(){
 const q=String(search?.value||'').trim().toLowerCase();
 const filtered=rows.filter(r=>!q||String(r.item).toLowerCase().includes(q));
 list.innerHTML=filtered.slice(0,500).map(r=>`<div class="asset-missing-row"><img src="/item-image.php?item=${encodeURIComponent(r.item)}" alt="" loading="lazy"><div><strong>${esc(r.item)}</strong><span>${esc(r.category||'onbekend')}</span></div></div>`).join('')||'<p class="muted">Geen ontbrekende items in deze selectie.</p>';
}
async function load(){
 try{
  const r=await fetch('/admin/assets-named-coverage',{cache:'no-store'}),d=await r.json();
  if(!r.ok||!d.ok)throw new Error(d.error||`HTTP ${r.status}`);
  total.textContent=d.catalog_items;linked.textContent=d.named_assets;missing.textContent=d.missing_named_assets;rows=d.missing||[];render();
 }catch(e){missing.textContent='?';}
}
show?.addEventListener('click',()=>{wrap.hidden=!wrap.hidden;if(!wrap.hidden)render();});
search?.addEventListener('input',render);
document.addEventListener('littywatch:named-assets-updated',load);
load();
})();