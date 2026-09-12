(function(){
'use strict';
var C=window.BubbaHubConfig||{},root=document.getElementById('bubbahub-app');
if(!root)return;
var cache=null;
function plain(v){return String(v==null?'':v).replace(/<[^>]*>/g,'').trim()}
function esc(v){return String(v==null?'':v).replace(/[&<>\"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[c]})}
function field(g,names){var f=g&&g.custom_fields||g&&g.fields||{};var wanted=names.map(function(n){return n.toLowerCase()});var found='';Object.keys(f).some(function(k){var x=f[k];var label=String(x&&x.label||k).toLowerCase();var value=x&&x.value!=null?x.value:x;if(wanted.indexOf(label)>-1&&String(value).trim()!==''){found=value;return true}return false});return found}
function chips(g){var a=[],loc=field(g,['town','city','location','area','region']),days=field(g,['days','day','timetable','business hours','opening hours']),age=field(g,['age range','age_range','ages','age']),price=field(g,['price','cost','pricing']);if(loc)a.push(['⌖ '+plain(loc),'']);if(days)a.push(['◷ '+plain(days),'is-green']);if(age)a.push(['♧ '+plain(age),'is-blue']);if(price)a.push(['£ '+plain(price),'is-purple']);return a.slice(0,4).map(function(v){return '<span class="bh-card-chip '+v[1]+'">'+esc(v[0])+'</span>'}).join('')}
function decorate(){var groups=cache||[];if(!groups.length)return;root.querySelectorAll('.bh-section>.bh-grid>.bh-card').forEach(function(card){if(card.dataset.bhPolished==='1')return;var h=card.querySelector('h3');if(!h)return;var title=plain(h.textContent);var g=groups.find(function(x){return plain(x.title)===title});if(!g)return;card.dataset.bhPolished='1';var children=Array.prototype.slice.call(card.children),tag=card.querySelector('.bh-tag'),actions=card.querySelector('.bh-card-actions');var visual=document.createElement('div');visual.className='bh-card-visual';if(g.image||g.featured_image){var img=document.createElement('img');img.src=plain(g.image||g.featured_image);img.alt='';img.loading='lazy';img.decoding='async';img.onerror=function(){img.remove();};visual.appendChild(img);card.classList.add('has-bh-image')};var badge=document.createElement('span');badge.className='bh-card-badge';badge.innerHTML='▦ '+esc(plain(tag&&tag.textContent||'Group'));visual.appendChild(badge);var icons=document.createElement('div');icons.className='bh-card-icons';var fav=actions&&actions.querySelector('[data-fav]');var visit=actions&&actions.querySelector('[data-visited]');var i1=document.createElement('button');i1.type='button';i1.className='bh-card-icon';i1.setAttribute('aria-label','Favourite group');i1.innerHTML='♡';if(fav)i1.onclick=function(e){e.preventDefault();e.stopPropagation();fav.click()};var i2=document.createElement('button');i2.type='button';i2.className='bh-card-icon';i2.setAttribute('aria-label','Mark group visited');i2.innerHTML='◫';if(visit)i2.onclick=function(e){e.preventDefault();e.stopPropagation();visit.click()};icons.appendChild(i1);icons.appendChild(i2);visual.appendChild(icons);if(tag)tag.remove();card.insertBefore(visual,card.firstChild);var inner=document.createElement('div');inner.className='bh-card-inner';children.forEach(function(x){if(x!==tag&&x!==visual)inner.appendChild(x)});var meta=document.createElement('div');meta.className='bh-card-meta';meta.innerHTML=chips(g);if(meta.innerHTML)h.insertAdjacentElement('afterend',meta);card.appendChild(inner);});}
function installUX(){
if(document.getElementById('bh-ux-refinements'))return;
var st=document.createElement('style');st.id='bh-ux-refinements';st.textContent=`
.bh-app,.bh-app *{font-family:inherit!important}.bh-app{font-size:16px}.bh-app .bh-card{font-size:16px}.bh-app .bh-card h3{font-size:1.18rem}.bh-app .bh-card p,.bh-app .bh-small{font-size:1rem;line-height:1.6}.bh-app .bh-btn{font-size:1rem}.bh-app .bh-input{font-size:1rem;padding:14px 16px}
.bh-myhub-stack{display:flex!important;flex-direction:column!important;gap:22px!important}.bh-myhub-stack>.bh-card{width:100%!important}.bh-myhub-stack>.bh-card:first-child{order:1}.bh-myhub-stack>.bh-card:nth-child(2){order:2}.bh-myhub-stack>.bh-card:nth-child(3){order:3}
.bh-myhub-children{display:flex;flex-direction:column;gap:14px}.bh-myhub-children .bh-child{border:1px solid var(--border);border-radius:16px;padding:16px;background:#fff;box-shadow:0 4px 14px rgba(26,46,34,.04)}.bh-myhub-children .bh-child:first-child{border-top:1px solid var(--border);padding-top:16px}
.bh-myhub-group-columns{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.bh-myhub-group-column{min-width:0}.bh-myhub-group-column h3{margin:0 0 14px;font-size:1.15rem}.bh-myhub-group-list{display:flex;flex-direction:column;gap:12px}.bh-myhub-group-list .bh-card{margin:0}.bh-myhub-group-empty{margin:0;color:var(--muted);font-size:1rem}.bh-myhub-group-column .bh-card{box-shadow:0 4px 14px rgba(26,46,34,.04)}
.bh-modal{cursor:pointer}.bh-modalbox{cursor:default}.bh-full-detail .bh-modalbox{font-size:16px}.bh-full-detail .bh-detail-heading h2{font-size:clamp(28px,4vw,42px)}
@media(max-width:800px){.bh-myhub-group-columns{grid-template-columns:1fr}.bh-app{font-size:16px}}
`;document.head.appendChild(st);
}
function myHubRefinements(){
var h=Array.prototype.find.call(root.querySelectorAll('.bh-pagehead h1'),function(x){return plain(x.textContent)==='My Hub'});if(!h)return;
var top=h.closest('.bh-main')&&h.closest('.bh-main').querySelector(':scope > .bh-grid');
if(top&&!top.classList.contains('bh-myhub-stack')){
 top.classList.add('bh-myhub-stack');
 var childCard=top.children[0];if(childCard){childCard.classList.add('bh-myhub-children-card');var list=childCard.querySelector('#childrenList');if(list)list.classList.add('bh-myhub-children');}
}
var savedTitle=Array.prototype.find.call(root.querySelectorAll('.bh-section-title h2'),function(x){return plain(x.textContent).toLowerCase()==='saved groups'});
var saved=savedTitle&&savedTitle.closest('.bh-section');
var visited=Array.prototype.find.call(root.querySelectorAll('.bh-section > h2'),function(x){return plain(x.textContent).toLowerCase()==='visited groups'})?.closest('.bh-section');
if(saved&&!saved.querySelector('.bh-myhub-group-columns')){
 var source=saved.querySelector(':scope > .bh-grid');var cards=source?Array.prototype.slice.call(source.querySelectorAll(':scope > .bh-card')):[];
 var visitedCards=visited?Array.prototype.slice.call(visited.querySelectorAll(':scope > .bh-grid > .bh-card')):[];
 var cols=document.createElement('div');cols.className='bh-myhub-group-columns';
 var names=['Saved groups','Favourite groups','Visited groups'];var sets=[cards,cards.map(function(card){return card.cloneNode(true)}),visitedCards];
 names.forEach(function(name,i){var col=document.createElement('div');col.className='bh-card bh-myhub-group-column';var title=document.createElement('h3');title.textContent=name;col.appendChild(title);var list=document.createElement('div');list.className='bh-myhub-group-list';sets[i].forEach(function(card){list.appendChild(card)});if(!sets[i].length){var empty=document.createElement('p');empty.className='bh-myhub-group-empty';empty.textContent=i===2?'No visited groups yet.':'No saved groups yet.';list.appendChild(empty)}col.appendChild(list);cols.appendChild(col)});
 cols.querySelectorAll('.bh-myhub-group-column:nth-child(2) [data-group-modal],.bh-myhub-group-column:nth-child(2) [data-fav],.bh-myhub-group-column:nth-child(2) [data-visited],.bh-myhub-group-column:nth-child(2) [data-compare]').forEach(function(btn){btn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();var key=btn.hasAttribute('data-group-modal')?'data-group-modal':btn.hasAttribute('data-fav')?'data-fav':btn.hasAttribute('data-visited')?'data-visited':'data-compare';var original=root.querySelector('.bh-myhub-group-column:first-child ['+key+'="'+btn.getAttribute(key)+'"]');if(original)original.click()})});
 if(source)source.remove();saved.appendChild(cols);if(visited)visited.remove();
}
root.querySelectorAll('.bh-child-tracker').forEach(function(t){var ps=t.querySelectorAll(':scope > p');if(ps.length>=3){var secondary=ps[2];if(secondary&&/secondary/i.test(secondary.textContent))secondary.remove();}});
}
function load(){if(cache){decorate();myHubRefinements();return}if(!C.api)return;fetch(C.api+'bootstrap',{headers:C.nonce?{'X-WP-Nonce':C.nonce}:{}}).then(function(r){return r.ok?r.json():null}).then(function(d){cache=d&&Array.isArray(d.groups)?d.groups:[];window.BubbaHubFigmaData=cache;decorate();myHubRefinements()}).catch(function(){});}
installUX();
new MutationObserver(function(){decorate();myHubRefinements()}).observe(root,{childList:true,subtree:true});load();
})();
