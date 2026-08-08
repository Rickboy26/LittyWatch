(()=>{
'use strict';
const btn=document.querySelector('[data-direct-assets]');
const status=document.querySelector('[data-direct-assets-status]');
if(!btn)return;

const base='https://raw.githubusercontent.com/Eta92/GW-Market/main';
const datasets=['consumable','exotic','material','miniature','rune','special','tome','unique','upgrade','weapon'];
const assetFolders=['consumable','currency','material','miniature','rune','special','tome','unique','upgrade','weapon'];
const say=t=>{if(status)status.textContent=t;};
const fileName=name=>String(name||'').trim().replace(/ /g,'_')+'.png';

function flatten(node, category, out=[]){
 if(Array.isArray(node)){
  for(const x of node)flatten(x,category,out);
  return out;
 }
 if(!node||typeof node!=='object')return out;
 if(typeof node.name==='string'&&node.name.trim()){
  out.push({name:node.name.trim(),category});
  return out;
 }
 for(const value of Object.values(node))if(value&&typeof value==='object')flatten(value,category,out);
 return out;
}
async function loadCatalog(){
 const all=[];
 for(let i=0;i<datasets.length;i++){
  const cat=datasets[i];
  say(`Broncatalogus ophalen · ${i+1}/${datasets.length} · ${cat}`);
  const r=await fetch(`${base}/server/data/${cat}.json`,{cache:'no-store',headers:{Accept:'application/json'}});
  if(!r.ok)throw new Error(`${cat}.json HTTP ${r.status}`);
  flatten(await r.json(),cat,all);
 }
 const seen=new Set();
 return all.filter(x=>{
  const k=x.name.toLowerCase();
  if(seen.has(k))return false;
  seen.add(k);return true;
 });
}
async function locate(item){
 const preferred=assetFolders.includes(item.category)?item.category:null;
 const folders=[preferred,...assetFolders].filter((v,i,a)=>v&&a.indexOf(v)===i);
 const encoded=encodeURIComponent(fileName(item.name)).replace(/%2F/gi,'/');
 for(const folder of folders){
  try{
   const r=await fetch(`${base}/assets/items/${folder}/${encoded}`,{cache:'force-cache'});
   if(!r.ok)continue;
   const blob=await r.blob();
   if(blob.size>20&&String(blob.type).includes('image'))return {folder,blob};
  }catch(_){}
 }
 return null;
}
const asDataUrl=blob=>new Promise((resolve,reject)=>{
 const reader=new FileReader();
 reader.onload=()=>resolve(String(reader.result));
 reader.onerror=()=>reject(reader.error||new Error('PNG lezen mislukt'));
 reader.readAsDataURL(blob);
});
async function store(item,hit){
 const body=new URLSearchParams({
  name:item.name,
  category:hit.folder,
  png:await asDataUrl(hit.blob)
 });
 const r=await fetch('/admin/assets-named-import',{
  method:'POST',
  headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},
  body:body.toString()
 });
 const d=await r.json().catch(()=>({ok:false,error:`HTTP ${r.status}`}));
 if(!r.ok||!d.ok)throw new Error(d.error||`HTTP ${r.status}`);
 return d;
}
btn.addEventListener('click',async()=>{
 btn.disabled=true;
 let saved=0,missing=0,errors=0,firstError='';
 try{
  const items=await loadCatalog();
  say(`${items.length} unieke GW1-items gevonden · inventory-assets koppelen…`);
  for(let i=0;i<items.length;i+=4){
   const group=items.slice(i,i+4);
   const hits=await Promise.all(group.map(locate));
   for(let j=0;j<group.length;j++){
    if(!hits[j]){missing++;continue;}
    try{
     const d=await store(group[j],hits[j]);
     saved+=Number(d.saved||0);
     if(Number(d.unknown||0)>0)missing++;
    }catch(e){errors++;if(!firstError)firstError=String(e?.message||e);}
   }
   say(`${Math.min(i+4,items.length)}/${items.length} · ${saved} lokaal opgeslagen · ${missing} niet gekoppeld · ${errors} fouten`);
  }
  say(`Klaar · ${saved} inventory icons lokaal opgeslagen · ${missing} niet gekoppeld · ${errors} fouten.${firstError?' Eerste fout: '+firstError:''}`);document.dispatchEvent(new Event('littywatch:named-assets-updated'));
 }catch(e){
  say(`Import gestopt: ${e?.message||e}`);
 }finally{
  btn.disabled=false;
 }
});
})();