(function(){
'use strict';
if(document.querySelector('.app-shell')) return; // main router already has unified layout
var body=document.body;if(!body)return;
var path=location.pathname;
var source=[].slice.call(body.childNodes);
var shell=document.createElement('div');shell.className='lw-app-shell';
var side=document.createElement('aside');side.className='lw-sidebar';side.innerHTML=`
<a class="lw-brand" href="/"><span class="lw-brand-mark">L</span><span><strong>LittyWatch</strong><small>GW1 Market Intelligence</small></span></a>
<span class="lw-nav-label">Markt</span><nav class="lw-nav">
<a href="/"><i>⌂</i>Dashboard</a><a href="/live"><i>●</i>Live feed</a><a href="/markets"><i>◆</i>Markten</a><a href="/items"><i>◇</i>Items</a><a href="/traders"><i>♟</i>Traders</a><a href="/trends"><i>↗</i>Trends</a><a href="/intelligence"><i>✦</i>Intelligence</a></nav>
<span class="lw-nav-label">Persoonlijk</span><nav class="lw-nav"><a href="/watchlist"><i>★</i>Watchlist</a><a href="/alerts"><i>!</i>Alerts</a></nav>
<span class="lw-nav-label">Beheer</span><nav class="lw-nav"><a href="/assets"><i>▧</i>Game assets</a><a href="/system"><i>⚙</i>Systeem</a><a href="/admin"><i>⌘</i>Beheer</a></nav>
<div class="lw-side-foot">LittyWatch<br>Eén platform · één interface</div>`;
var area=document.createElement('div');area.className='lw-main-area';
var top=document.createElement('header');top.className='lw-topbar';
var title=(document.querySelector('h1')||{}).textContent||document.title.replace(/^LittyWatch\s*/,'')||'LittyWatch';
top.innerHTML='<div><button class="lw-mobile" aria-label="Menu">☰</button> <span class="lw-page-name"></span></div><span class="lw-top-meta">Kamadan · America English 1</span>';
top.querySelector('.lw-page-name').textContent=title.trim();
var content=document.createElement('div');content.className='lw-page-content';
source.forEach(function(n){if(n!==shell)content.appendChild(n)});
area.appendChild(top);area.appendChild(content);shell.appendChild(side);shell.appendChild(area);body.appendChild(shell);body.classList.add('lw-unified');
var aliases={'/market':'/markets','/item':'/items','/trader':'/traders'};var active=aliases[path]||path;
side.querySelectorAll('a').forEach(function(a){var p=new URL(a.href,location.href).pathname;if(p===active||(p==='/'&&path==='/'))a.classList.add('active')});
top.querySelector('.lw-mobile').addEventListener('click',function(){side.classList.toggle('open')});
})();
