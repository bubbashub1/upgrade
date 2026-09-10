(function(){
  const C=window.BubbaHubStatusConfig||{};
  const root=document.getElementById('bubbahub-app');
  if(!root||!C.api)return;
  const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','\"':'&quot;'}[c]));
  const cache={};
  function badge(status){
    if(!status||!status.key)return '';
    return '<span class="bh-group-status-badge '+esc(status.class||'')+'" data-group-status="'+esc(status.key)+'"><span class="bh-status-dot" aria-hidden="true"></span>'+esc(status.label||'')+'</span>';
  }
  function decorate(){
    root.querySelectorAll('[data-group-modal]').forEach(btn=>{
      const id=String(btn.getAttribute('data-group-modal')||'');
      const status=cache[id];
      const card=btn.closest('.bh-card');
      if(!card||!status)return;
      let existing=card.querySelector('.bh-group-status-badge');
      if(!existing){
        const title=card.querySelector('h3,h2');
        if(title) title.insertAdjacentHTML('beforebegin',badge(status));
      } else existing.outerHTML=badge(status);
    });
    const detail=root.querySelector('.bh-group-detail');
    const heading=root.querySelector('.bh-group-detail h1,.bh-group-detail h2');
    if(detail&&heading){
      const idNode=root.querySelector('[data-group-status-id]');
      const id=idNode&&idNode.getAttribute('data-group-status-id');
      if(id&&cache[id]&&!detail.querySelector('.bh-group-status-badge')) heading.insertAdjacentHTML('beforebegin',badge(cache[id]));
    }
  }
  fetch(C.api,{headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():[]).then(rows=>{
    (Array.isArray(rows)?rows:[]).forEach(g=>{if(g&&g.id)cache[String(g.id)]=g.activity_status;});
    decorate();
    new MutationObserver(decorate).observe(root,{childList:true,subtree:true});
  }).catch(()=>{});
})();