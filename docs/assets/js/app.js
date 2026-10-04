(()=>{'use strict';
const script=document.currentScript;const root=script?.dataset.root||'assets';
const body=document.body;
const navToggle=document.querySelector('[data-nav-toggle]');const scrim=document.querySelector('.mobile-scrim');const sidebar=document.querySelector('.sidebar');const navMedia=matchMedia('(max-width: 760px)');
const activeSidebarLink=sidebar?.querySelector('.nav-link.active');
const syncNavigationState=()=>{if(!navMedia.matches)body.classList.remove('nav-open');const open=navMedia.matches&&body.classList.contains('nav-open');const hidden=navMedia.matches&&!open;if(sidebar){sidebar.inert=hidden;sidebar.setAttribute('aria-hidden',String(hidden))}scrim?.setAttribute('aria-hidden',String(!open));navToggle?.setAttribute('aria-expanded',String(open));navToggle?.setAttribute('aria-label',open?'Fechar menu':'Abrir menu')};
const revealActiveSidebarLink=()=>{if(!sidebar||!activeSidebarLink)return;const side=sidebar.getBoundingClientRect(),link=activeSidebarLink.getBoundingClientRect(),visibleTop=side.top+72,visibleBottom=side.bottom-12;if(link.top<visibleTop||link.bottom>visibleBottom){const center=visibleTop+Math.max(0,(visibleBottom-visibleTop)/2);sidebar.scrollTop=Math.max(0,sidebar.scrollTop+link.top-center)}};
const closeNav=(restoreFocus=false)=>{body.classList.remove('nav-open');syncNavigationState();if(restoreFocus)navToggle?.focus()};
navToggle?.addEventListener('click',()=>{if(!navMedia.matches)return;if(body.classList.contains('nav-open')){closeNav(true);return}body.classList.add('nav-open');syncNavigationState();requestAnimationFrame(()=>{revealActiveSidebarLink();(activeSidebarLink||sidebar?.querySelector('.nav-link'))?.focus({preventScroll:true})})});
scrim?.addEventListener('click',()=>closeNav(true));
sidebar?.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>closeNav(false)));
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&body.classList.contains('nav-open')){event.preventDefault();closeNav(true)}});
syncNavigationState();requestAnimationFrame(revealActiveSidebarLink);navMedia.addEventListener('change',()=>{syncNavigationState();requestAnimationFrame(revealActiveSidebarLink)});window.addEventListener('resize',()=>requestAnimationFrame(revealActiveSidebarLink),{passive:true});
const themeButton=document.querySelector('[data-theme-toggle]');let saved='';try{saved=localStorage.getItem('docs-theme')||''}catch{}if(saved==='dark')body.classList.add('theme-dark');themeButton?.addEventListener('click',()=>{body.classList.toggle('theme-dark');try{localStorage.setItem('docs-theme',body.classList.contains('theme-dark')?'dark':'light')}catch{}});
const input=document.querySelector('[data-search]');const results=document.querySelector('[data-results]');let index=window.DOC_SEARCH_INDEX||[];let active=-1;
const norm=s=>(s||'').toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g,'');
function show(items,q){if(!results)return;results.replaceChildren();active=-1;if(!q){results.classList.remove('open');return}if(!items.length){results.innerHTML='<div class="search-empty">Nenhum resultado. Tente classe, método ou recurso.</div>';results.classList.add('open');return}for(const item of items.slice(0,9)){const a=document.createElement('a');a.className='search-result';a.href=root+'/../'+item.path;a.innerHTML='<strong></strong><small></small>';a.querySelector('strong').textContent=item.title;a.querySelector('small').textContent=item.section+' · '+item.excerpt.slice(0,100);results.append(a)}results.classList.add('open')}
input?.addEventListener('input',()=>{const q=norm(input.value.trim());if(!q){show([],q);return}const terms=q.split(/\s+/);const scored=index.map(item=>{const hay=norm(item.title+' '+item.section+' '+item.terms+' '+item.excerpt);let score=0;for(const t of terms){if(norm(item.title).includes(t))score+=8;if(norm(item.terms).includes(t))score+=4;if(norm(item.section).includes(t))score+=2;if(hay.includes(t))score++}return [item,score]}).filter(x=>x[1]>=terms.length).sort((a,b)=>b[1]-a[1]).map(x=>x[0]);show(scored,q)});
input?.addEventListener('keydown',e=>{const links=[...(results?.querySelectorAll('a')||[])];if(e.key==='Escape'){results?.classList.remove('open');input.blur()}if(e.key==='ArrowDown'&&links.length){e.preventDefault();active=(active+1)%links.length;links[active].focus()}if(e.key==='Enter'&&links.length){if(document.activeElement===input)links[0].click()}});
document.addEventListener('click',e=>{if(!e.target.closest('.header-search'))results?.classList.remove('open')});document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();input?.focus()}if(e.key==='/'&&!['INPUT','TEXTAREA'].includes(document.activeElement.tagName)){e.preventDefault();input?.focus()}});
// Lightweight offline syntax highlighting for snippets; never evaluates code.
const keywords=new Set(('abstract and array as break callable case catch class clone const continue declare default die do echo else elseif empty enddeclare endfor endforeach endif endswitch endwhile enum eval exit extends final finally fn for foreach function global goto if implements include include_once instanceof insteadof interface isset list match namespace new or print private protected public readonly require require_once return static switch throw trait try unset use var while xor yield true false null self parent void never int string bool float mixed object array').split(' '));
const esc=s=>s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
function highlight(src){const re=/(\/\*[\s\S]*?\*\/|\/\/[^\n]*|#[^\n]*|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\$[A-Za-z_][\w]*|<\?php|\?>|\b\d+(?:\.\d+)?\b|\b[A-Za-z_][\w]*\b)/g;let out='',last=0,m;while((m=re.exec(src))){out+=esc(src.slice(last,m.index));const t=m[0];let c='';if(t.startsWith('//')||t.startsWith('#')||t.startsWith('/*'))c='token-comment';else if(t[0]==='"'||t[0]==="'")c='token-string';else if(t[0]==='$')c='token-variable';else if(t==='<?php'||t==='?>')c='token-tag';else if(/^\d/.test(t))c='token-number';else if(keywords.has(t))c='token-keyword';else if(/^[A-Za-z_]/.test(t)&&src.slice(re.lastIndex).match(/^\s*\(/))c='token-function';out+=c?'<span class="'+c+'">'+esc(t)+'</span>':esc(t);last=re.lastIndex}return out+esc(src.slice(last))}

// API reference category tabs and deep class/method navigation.
const apiNavigator=document.querySelector('[data-api-navigator]');
if(apiNavigator){
 const doc=document.documentElement;
 const apiFilter=apiNavigator.querySelector('[data-api-filter]');
 const apiResults=apiNavigator.querySelector('[data-api-results]');
 const apiStatus=apiNavigator.querySelector('[data-api-status]');
 const apiTabs=[...apiNavigator.querySelectorAll('[data-api-group]')];
 const apiHeadings=[...document.querySelectorAll('.prose h2[id^="group-"]')];
 const apiCards=[...document.querySelectorAll('.prose details.api-class')].map(card=>{
  let node=card.previousElementSibling;while(node&&!node.matches('h2[id^="group-"]'))node=node.previousElementSibling;
  return {card,group:node?.id||''};
 });
 let apiGroup='all';
 doc.classList.add('api-reference-page','api-nav-active');body.classList.add('api-nav-active');
 const syncAnchorOffset=()=>{
  const top=parseFloat(getComputedStyle(apiNavigator).top)||0;
  const height=apiNavigator.getBoundingClientRect().height;
  doc.style.setProperty('--api-anchor-offset',`${Math.ceil(top+height+14)}px`);
 };
 const scrollToTarget=(target,behavior='smooth',focus=false)=>{
  if(!target)return;
  requestAnimationFrame(()=>requestAnimationFrame(()=>{
   syncAnchorOffset();
   const header=parseFloat(getComputedStyle(doc).getPropertyValue('--header'))||68;
   const panelTop=parseFloat(getComputedStyle(apiNavigator).top)||header+8;
   const offset=apiNavigator.contains(target)?header+12:panelTop+apiNavigator.getBoundingClientRect().height+14;
   const top=Math.max(0,window.scrollY+target.getBoundingClientRect().top-offset);
   const instant=behavior!=='smooth'||matchMedia('(prefers-reduced-motion: reduce)').matches;
   if(instant){const scroller=document.scrollingElement||doc;scroller.scrollTop=top;}
   else window.scrollTo({top,behavior:'smooth'});
   if(focus){
    const focusTarget=target.matches('details.api-class')?target.querySelector('summary'):target.matches('.api-method-doc')?target.querySelector('h3'):target;
    if(focusTarget){if(focusTarget.tagName==='H3')focusTarget.tabIndex=-1;focusTarget.focus({preventScroll:true});}
   }
  }));
 };
 const setHash=target=>{if(target?.id)history.pushState(null,'',`#${encodeURIComponent(target.id)}`)};
 const renderApiIndex=()=>{
  const query=norm(apiFilter.value.trim());
  const terms=query?query.split(/\s+/):[];
  const matches=[];
  for(const item of apiCards){
   const card=item.card;
   const groupMatch=apiGroup==='all'||item.group===apiGroup;
   const fullText=norm(card.textContent);
   const fullMatch=!!query&&terms.every(term=>fullText.includes(term));
   const classText=norm(`${card.querySelector('summary')?.textContent||''} ${card.querySelector('.source-path')?.textContent||''}`);
   const classMatch=!!query&&terms.every(term=>classText.includes(term));
   const methodHits=query?[...card.querySelectorAll('.api-method-doc')].filter(method=>{
    const text=norm(`${method.id} ${method.textContent}`);return terms.every(term=>text.includes(term));
   }):[];
   const queryMatch=!query||classMatch||methodHits.length>0||fullMatch;
   const visible=groupMatch&&queryMatch;
   card.hidden=!visible;
   if(!visible)continue;
   const className=card.querySelector('summary code')?.textContent.trim()||card.id;
   if(!query||classMatch)matches.push({target:card,label:className});
   for(const method of methodHits){
    const methodName=method.querySelector('h3 code')?.textContent.trim()||method.id;
    matches.push({target:method,label:`${className} · ${methodName}`});
   }
   if(query&&!classMatch&&!methodHits.length&&fullMatch)matches.push({target:card,label:className});
  }
  for(const heading of apiHeadings){
   heading.hidden=!apiCards.some(item=>item.group===heading.id&&!item.card.hidden);
  }
  for(const tab of apiTabs){
   if(tab.dataset.apiGroup===apiGroup)tab.setAttribute('aria-current','location');
   else tab.removeAttribute('aria-current');
  }
  const active=apiGroup!=='all'||query.length>0;
  apiStatus.hidden=!active;apiResults.hidden=!active;
  if(active){
   apiStatus.textContent=matches.length===1?'1 destino encontrado':`${matches.length} destinos encontrados`;
   apiResults.replaceChildren();
   if(matches.length){
    for(const match of matches){
     const li=document.createElement('li');const link=document.createElement('a');
     link.href=`#${encodeURIComponent(match.target.id)}`;link.textContent=match.label;
     link.addEventListener('click',event=>{
      event.preventDefault();apiGroup='all';apiFilter.value='';renderApiIndex();
      const card=match.target.closest('details.api-class');if(card)card.open=true;
      setHash(match.target);scrollToTarget(match.target,'smooth',true);
     });
     li.append(link);apiResults.append(li);
    }
   }else{
    const li=document.createElement('li');li.dataset.empty='';li.textContent='Nenhuma classe, método ou função corresponde ao filtro.';apiResults.append(li);
   }
  }
  requestAnimationFrame(syncAnchorOffset);
 };
 const openCurrentHash=(behavior='smooth')=>{
  let id;try{id=decodeURIComponent(location.hash.slice(1))}catch{return}
  const target=id?document.getElementById(id):null;if(!target)return;
  if(target.matches('h2[id^="group-"]'))apiGroup=target.id;
  else apiGroup='all';
  apiFilter.value='';renderApiIndex();
  const card=target.closest('details.api-class');if(card)card.open=true;
  scrollToTarget(target,behavior,false);
 };
 for(const tab of apiTabs){tab.addEventListener('click',event=>{
  event.preventDefault();apiGroup=tab.dataset.apiGroup;apiFilter.value='';renderApiIndex();
  const target=apiGroup==='all'?apiNavigator:document.getElementById(apiGroup);
  if(target){setHash(target);scrollToTarget(target,'smooth',false);}
 });}
 apiFilter.addEventListener('input',renderApiIndex);
 apiFilter.addEventListener('keydown',event=>{if(event.key==='Escape'&&apiFilter.value){apiFilter.value='';renderApiIndex()}});
 window.addEventListener('hashchange',()=>openCurrentHash('smooth'));
 window.addEventListener('resize',syncAnchorOffset,{passive:true});
 renderApiIndex();syncAnchorOffset();
 if(location.hash)openCurrentHash('instant');
}

document.querySelectorAll('pre > code').forEach(code=>{const pre=code.parentElement;const frame=document.createElement('div');frame.className='code-frame';pre.parentNode.insertBefore(frame,pre);frame.append(pre);const toolbar=document.createElement('div');toolbar.className='code-toolbar';frame.insertBefore(toolbar,pre);const lang=[...code.classList].find(x=>x.startsWith('language-'))?.replace('language-','')||'code';const label=document.createElement('span');label.className='code-label';label.textContent=lang;toolbar.append(label);const button=document.createElement('button');button.className='copy-btn';button.type='button';button.textContent='Copiar';button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(code.textContent);button.textContent='Copiado';setTimeout(()=>button.textContent='Copiar',1500)}catch{const r=document.createRange();r.selectNodeContents(code);const s=getSelection();s.removeAllRanges();s.addRange(r);document.execCommand('copy');s.removeAllRanges();button.textContent='Copiado';setTimeout(()=>button.textContent='Copiar',1500)}});toolbar.append(button);code.innerHTML=highlight(code.textContent)});
})();
