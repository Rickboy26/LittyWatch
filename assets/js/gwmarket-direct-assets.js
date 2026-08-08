(()=>{
const btn=document.querySelector('[data-direct-assets]'),status=document.querySelector('[data-direct-assets-status]');
if(!btn)return;
const raw='https://raw.githubusercontent.com/Eta92/GW-Market/main/assets/items';
const categories=['consumable','currency','material','miniature','rune','special','tome','unique','upgrade','weapon'];
const say=t=>{if(status)status.textContent=t;};
const filename=n=>String(n).trim().replace(/ /g,'_')+'.png';
const b64=blob=>new Promise((resolve,reject)=>{const r=new FileReader();r.onload=()=>resolve(String(r.result));r.onerror=reject;r.readAsDataURL(blob);});
async function locate(item){
 const preferred=String(item.category||'').toLowerCase();
 const cats=[preferred,...categories].filter((v,i,a)=>v&&a.indexOf(v)===i);
 for(const c of cats){
  const u=`${raw}/${c}/${encodeURIComponent(filename(item.name)).replace(/%2F/gi,'/')}`;
  try{const r=await fetch(u,{cache:'force-cache'});if(!r.ok)continue;const blob=await r.blob();if(blob.size>20&&blob.type.includes('image'))return {category:c,blob};}catch(_){}
 }
 return null;
}
async function save(item,hit){
 const body=new URLSearchParams({name:item.name,category:hit.category,png:await b64(hit.blob)});
 const r=await fetch('/admin/assets-named-import',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},body});
 const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||`HTTP ${r.status}`);return d;
}
btn.addEventListener('click',async()=>{
 btn.disabled=true;let found=0,missing=0,errors=0;
 try{
  const cr=await fetch('/admin/assets-named-catalog',{cache:'no-store'}),cd=await cr.json();
  if(!cr.ok||!cd.ok)throw new Error(cd.error||'Catalogus niet beschikbaar');
  const items=cd.items||[];
  for(let i=0;i<items.length;i+=4){
   const group=items.slice(i,i+4);
   const hits=await Promise.all(group.map(locate));
   for(let j=0;j<group.length;j++){
    const hit=hits[j];if(!hit){missing++;continue;}
    try{await save(group[j],hit);found++;}catch(_){errors++;}
   }
   say(`${Math.min(i+4,items.length)}/${items.length} · ${found} inventory icons lokaal opgeslagen · ${missing} niet gevonden · ${errors} fouten`);
  }
  say(`Klaar · ${found} juiste inventory icons lokaal opgeslagen · ${missing} niet gevonden · ${errors} fouten. LittyWatch gebruikt deze nu direct op naam.`);
 }catch(e){say('Import gestopt: '+e.message)}finally{btn.disabled=false;}
});
})();