(()=>{
'use strict';
const button=document.querySelector('[data-gwm-import]');
if(!button)return;
const status=document.querySelector('[data-gwm-status]');
const categories=['consumable','exotic','material','miniature','rune','special','tome','unique','upgrade','weapon'];
const rawBase='https://raw.githubusercontent.com/Eta92/GW-Market/main/server/data';

const say=(text,bad=false)=>{
  if(!status)return;
  status.textContent=text;
  status.classList.toggle('error',!!bad);
};

async function postCategory(category,json){
  const body=new URLSearchParams({category,json});
  const response=await fetch('/knowledge/gw-market-import',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},
    body:body.toString()
  });
  const data=await response.json().catch(()=>({ok:false,error:`HTTP ${response.status}`}));
  if(!response.ok||!data.ok)throw new Error(data.error||`Import ${category} mislukt`);
  return data;
}

button.addEventListener('click',async()=>{
  if(button.disabled)return;
  button.disabled=true;
  let files=0,written=0,seen=0;
  try{
    for(const category of categories){
      say(`GW Market catalogus ophalen: ${category} · ${files}/${categories.length}`);
      const url=`${rawBase}/${category}.json`;
      const response=await fetch(url,{cache:'no-store',headers:{'Accept':'application/json'}});
      if(!response.ok)throw new Error(`${category}.json: HTTP ${response.status}`);
      const json=await response.text();
      const result=await postCategory(category,json);
      files++; written+=Number(result.written||0); seen+=Number(result.seen||0);
      say(`${files}/${categories.length} datasets · ${written} items geïmporteerd`);
    }
    say(`Klaar · ${files} datasets · ${seen} records gelezen · ${written} GW1-items in LittyWatch bijgewerkt. Herlaad de pagina voor de nieuwe totalen.`);
  }catch(error){
    say(`Import gestopt: ${error?.message||error}`,true);
  }finally{
    button.disabled=false;
  }
});
})();