(function(){
'use strict';
const root=document.getElementById('bubbahub-app'); if(!root)return;
const esc=s=>String(s??'').replace(/[&<>\'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
const getField=(g,...keys)=>{for(const k of keys){let v=g?.custom_fields?.[k];if(v&&typeof v==='object'&&'value' in v)v=v.value;if(v!==''&&v!=null)return v;}return '';};
const website=g=>getField(g,'website','url','website_url')||g?.link||'#';
const termTime=g=>{const v=String(getField(g,'term_time','term-time','term_time_only','termTime')).toLowerCase().trim();return v&&!['0','no','false','off','none'].includes(v);};
let groups=[];
function enhance(){
 root.querySelectorAll('.bh-directory-card').forEach(card=>{
  if(card.dataset.bhActionsReady==='1')return;
  const id=card.dataset.listingId,g=groups.find(x=>String(x.id)===String(id)); if(!g)return;
  card.dataset.bhActionsReady='1';
  const body=card.querySelector('.bh-listing-body'),save=card.querySelector('.bh-listing-save'),view=card.querySelector('.bh-listing-view'); if(!body||!view)return;
  let actions=body.querySelector('.bh-card-actions-modern'); if(!actions){actions=document.createElement('div');actions.className='bh-card-actions-modern';body.appendChild(actions);}
  if(save){save.className='bh-listing-save bh-action-button';save.textContent='♡';save.setAttribute('aria-label','Favourite listing');actions.appendChild(save);}
  const compare=document.createElement('button');compare.type='button';compare.className='bh-action-button bh-compare-button';compare.textContent='Compare';compare.dataset.compareId=id;actions.appendChild(compare);
  const visit=document.createElement('a');visit.className='bh-action-button bh-visit-button';visit.textContent='Visit';visit.href=website(g);visit.target='_blank';visit.rel='noopener';actions.appendChild(visit);
  view.classList.add('bh-primary-details');
  if(termTime(g)&&!card.querySelector('.bh-badge-term')){const badges=card.querySelector('.bh-card-badges');if(badges){const b=document.createElement('span');b.className='bh-badge bh-badge-term';b.textContent='Term time';badges.appendChild(b);}}
  const selected=JSON.parse(localStorage.getItem('bubbahub-compare')||'[]').map(String);compare.classList.toggle('is-selected',selected.includes(String(id)));
  compare.onclick=()=>{let ids=JSON.parse(localStorage.getItem('bubbahub-compare')||'[]').map(String),sid=String(id);if(ids.includes(sid))ids=ids.filter(x=>x!==sid);else{if(ids.length>=3){alert('You can compare up to 3 listings.');return;}ids.push(sid);}localStorage.setItem('bubbahub-compare',JSON.stringify(ids));compare.classList.toggle('is-selected',ids.includes(sid));};
 });
}
async function init(){try{const C=window.BubbaHubConfig||{},r=await fetch(C.api+'bootstrap',{headers:C.nonce?{'X-WP-Nonce':C.nonce}:{}}),d=await r.json();groups=d.groups||[];enhance();new MutationObserver(enhance).observe(root,{childList:true,subtree:true});}catch(e){console.warn('BubbaHub card actions:',e);}}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(init,250));else setTimeout(init,250);
})();
