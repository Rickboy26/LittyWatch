(()=>{
const btn=document.querySelector('[data-gwca-import]'), file=document.querySelector('[data-runtime-file]'), status=document.querySelector('[data-gameid-status]');
if(!btn)return;
const say=t=>{if(status)status.textContent=t;};
async function post(url,rows){
 const body=new URLSearchParams({rows:JSON.stringify(rows)});
 const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},body});
 const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||`HTTP ${r.status}`);return d;
}
btn.addEventListener('click',async()=>{
 btn.disabled=true;try{
  const r=await fetch('/assets/data/gwca-item-model-ids.json?v=3o',{cache:'no-store'}),rows=await r.json();
  const d=await post('/admin/assets-gwca-ids',rows);
  say(`GWCA model-ID's: ${d.matched} gekoppeld · ${d.unmatched} niet gevonden. Dit zijn item model-ID's, nog geen icon-DAT-ID's.`);
 }catch(e){say('Fout: '+e.message)}finally{btn.disabled=false;}
});
file?.addEventListener('change',async()=>{
 const f=file.files?.[0];if(!f)return;
 try{
  const rows=JSON.parse(await f.text()), d=await post('/admin/assets-runtime-ids',rows);
  say(`Runtime export: ${d.updated} items bijgewerkt · ${d.icon_links} exacte lokale iconkoppelingen · ${d.missing} niet verwerkt.`);
 }catch(e){say('Fout: '+e.message)}
});
})();