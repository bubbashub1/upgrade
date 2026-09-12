(function(){
  'use strict';
  var root=document.getElementById('bubbahub-app');
  if(!root)return;

  function plain(v){return String(v==null?'':v).replace(/<[^>]*>/g,'').trim();}
  function esc(v){return String(v==null?'':v).replace(/[&<>\"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[c]});}
  function field(g,names){
    var f=g&&g.custom_fields||g&&g.fields||{};
    var wanted=names.map(function(n){return n.toLowerCase()});
    var found='';
    Object.keys(f).some(function(k){
      var x=f[k],label=String(x&&x.label||k).toLowerCase(),value=x&&x.value!=null?x.value:x;
      if(wanted.indexOf(label)>-1&&String(value).trim()!==''){found=value;return true;}
      return false;
    });
    return found;
  }
  function data(){return Array.isArray(window.BubbaHubFigmaData)?window.BubbaHubFigmaData:[];}
  function loc(g){return plain(field(g,['town','city','location','area','region']))||'Devon & Cornwall';}
  function age(g){return plain(field(g,['age range','age_range','ages','age']))||'0 – 5 years';}
  function price(g){return plain(field(g,['price','cost','pricing']))||'Free';}
  function days(g){return plain(field(g,['days','day','timetable','business hours','opening hours']))||'Various times';}
  function type(g){return plain((g.terms||[]).join(' · '))||'Group';}
  function image(g){return plain(g.image||g.featured_image||'');}
  function about(g){return plain(g.content||g.description||g.excerpt||'A friendly local group for families.');}

  function card(g){
    var img=image(g);
    return '<article class="bh-community-card" data-community-group="'+esc(g.id)+'">'+
      '<div class="bh-community-card-media">'+(img?'<img src="'+esc(img)+'" alt="" loading="lazy">':'')+
      '<div class="bh-community-card-icons"><button class="bh-community-icon" type="button" aria-label="Favourite">♡</button></div></div>'+ 
      '<div class="bh-community-card-body">'+
      '<h3>'+esc(g.title)+'</h3>'+ 
      '<div class="bh-community-location">⌖ '+esc(loc(g))+'</div>'+ 
      '<div class="bh-community-meta"><span>♧ '+esc(age(g))+'</span><span>£ '+esc(price(g))+'</span></div>'+ 
      '<div class="bh-community-actions"><button class="primary" type="button" data-community-details="'+esc(g.id)+'">View details →</button></div>'+ 
      '</div></article>';
  }

  function detail(g){
    if(!g)return '';
    var img=image(g),d=days(g),a=age(g),p=price(g),l=loc(g),t=type(g),content=about(g);
    return '<div class="bh-community-modal" data-community-modal="'+esc(g.id)+'" role="dialog" aria-modal="true" aria-labelledby="bh-community-modal-title-'+esc(g.id)+'">'+
      '<div class="bh-community-modal-backdrop" data-community-close></div>'+
      '<section class="bh-community-detail">'+
      '<button class="bh-community-modal-close" type="button" data-community-close aria-label="Close details">×</button>'+ 
      '<div class="bh-community-detail-image">'+(img?'<img src="'+esc(img)+'" alt="">':'')+'</div>'+ 
      '<div class="bh-community-detail-head"><div class="bh-community-detail-location">⌖ '+esc(l)+'</div><h2 id="bh-community-modal-title-'+esc(g.id)+'" class="bh-community-detail-title">'+esc(g.title)+'</h2>'+ 
      '<div class="bh-community-badges"><div class="bh-community-badge play">♧<small>Play</small></div><div class="bh-community-badge social">♧<small>Social</small></div><div class="bh-community-badge support">♡<small>Support</small></div><div class="bh-community-badge age">♟<small>'+esc(a)+'</small></div></div></div>'+ 
      '<div class="bh-community-detail-pills"><span>▦ '+esc(d)+'</span><span>♧ '+esc(a)+'</span><span>£ '+esc(p)+'</span></div>'+ 
      '<div class="bh-community-detail-grid"><div class="bh-community-info">'+
      '<div class="bh-community-info-row"><div class="bh-community-info-icon">▦</div><div><strong>When & how often</strong><span>'+esc(d)+'</span></div></div>'+ 
      '<div class="bh-community-info-row"><div class="bh-community-info-icon">♧</div><div><strong>Age range</strong><span>'+esc(a)+'</span></div></div>'+ 
      '<div class="bh-community-info-row"><div class="bh-community-info-icon">£</div><div><strong>Cost</strong><span>'+esc(p)+'</span></div></div>'+ 
      '<div class="bh-community-info-row"><div class="bh-community-info-icon">◎</div><div><strong>Group type</strong><span>'+esc(t)+'</span></div></div>'+ 
      '<div class="bh-community-info-row"><div class="bh-community-info-icon">i</div><div><strong>About</strong><span>'+esc(content)+'</span></div></div></div>'+ 
      '<aside class="bh-community-side"><h4>Opening times</h4><div class="bh-community-opening"><span>Sessions</span><strong>'+esc(d)+'</strong></div><div class="bh-community-opening"><span>Age</span><strong>'+esc(a)+'</strong></div><h4 style="margin-top:14px">Location</h4><div class="bh-community-map">⌖ '+esc(l)+'</div></aside></div>'+ 
      '<div class="bh-community-contact"><div><strong>Want to know more?</strong><span>Get in touch with the group organiser for the latest updates.</span></div><button type="button" data-community-contact="'+esc(g.id)+'">Contact group →</button></div>'+ 
      '<div class="bh-community-bottom-actions"><button type="button" data-community-fav="'+esc(g.id)+'">☆ Favourite</button><button type="button" data-community-visited="'+esc(g.id)+'">▣ Mark visited</button><a class="primary" href="'+esc(g.link||'#')+'">View website →</a></div></section></div>';
  }

  function build(){
    var isDirectory=!!root.querySelector('.bh-pagehead')&&/Find groups/i.test(root.textContent||'');
    var isHome=!!root.querySelector('.bh-hero')&&!!root.querySelector('.bh-section');
    if(!isDirectory&&!isHome)return;
    if(root.querySelector('.bh-community-shell'))return;
    var groups=data();
    if(!groups.length)return;
    root.classList.add('bh-app-community-ready');
    var shell=document.createElement('div');shell.className='bh-community-shell';
    shell.innerHTML='<div class="bh-community-content">'+
      '<section class="bh-community-hero"><div><div class="bh-community-eyebrow">Your family, your hub</div><h1 class="bh-community-title">Find your people. Plan your days. Feel supported.</h1><p class="bh-community-subtitle">Discover local groups, activities and support services for you and your family across Devon & Cornwall.</p>'+ 
      '<div class="bh-community-search-row"><label class="bh-community-control"><span class="control-icon">⌕</span><input id="bh-community-search" type="search" placeholder="Search groups, activities or support..." aria-label="Search groups"></label><label class="bh-community-control"><span class="control-icon">⌖</span><span>Location</span><button type="button">⌄</button></label><label class="bh-community-control"><span class="control-icon">♧</span><span>Age range</span><button type="button">⌄</button></label><label class="bh-community-control"><span class="control-icon">◈</span><span>Category</span><button type="button">⌄</button></label></div></div><aside class="bh-community-planning"><h3>School & antenatal planning</h3><p>Add a child DOB or expected due date to see your next key dates.</p><a href="'+esc((window.BubbaHubConfig&&window.BubbaHubConfig.pageUrls&&window.BubbaHubConfig.pageUrls.myhub)||'#')+'">Open My Hub →</a></aside></section>'+ 
      '<h2 class="bh-community-featured-title">Featured groups</h2><section class="bh-community-layout"><div class="bh-community-list" id="bh-community-list"></div></section></div>';
    root.innerHTML='';root.appendChild(shell);renderList(groups);bind(shell);
  }

  function renderList(groups){
    var list=root.querySelector('#bh-community-list');if(!list)return;
    list.innerHTML=groups.slice(0,12).map(card).join('')||'<div class="bh-community-empty">No groups found.</div>';
  }

  function open(g){
    var old=root.querySelector('.bh-community-modal');if(old)old.remove();
    var holder=document.createElement('div');holder.innerHTML=detail(g);var modal=holder.firstElementChild;if(!modal)return;
    document.body.appendChild(modal);document.body.classList.add('bh-community-modal-open');
    requestAnimationFrame(function(){modal.classList.add('is-open');});
    var close=modal.querySelector('[data-community-close]');if(close)close.focus();
  }

  function closeModal(){
    var modal=root.querySelector('.bh-community-modal');
    if(!modal)modal=document.querySelector('.bh-community-modal');
    if(!modal)return;
    modal.classList.remove('is-open');
    setTimeout(function(){if(modal&&modal.parentNode)modal.parentNode.removeChild(modal);document.body.classList.remove('bh-community-modal-open');},160);
  }

  function bind(scope){
    scope.addEventListener('click',function(e){
      var detailBtn=e.target.closest('[data-community-details]');
      if(detailBtn){e.preventDefault();e.stopPropagation();var g=data().find(function(x){return +x.id===+detailBtn.getAttribute('data-community-details')});if(g)open(g);return;}
      if(e.target.closest('[data-community-close]')){e.preventDefault();closeModal();return;}
      var contact=e.target.closest('[data-community-contact]');
      if(contact){var g=data().find(function(x){return +x.id===+contact.getAttribute('data-community-contact')});if(g&&(g.website||g.link))window.open(g.website||g.link,'_blank','noopener');}
    },true);
    var input=scope.querySelector('#bh-community-search');
    if(input)input.addEventListener('input',function(){var q=plain(input.value).toLowerCase();var filtered=data().filter(function(g){return !q||[g.title,g.excerpt,g.content,(g.terms||[]).join(' '),loc(g),age(g),price(g)].join(' ').toLowerCase().indexOf(q)>-1});scope.querySelector('#bh-community-list').innerHTML=filtered.slice(0,12).map(card).join('')||'<div class="bh-community-empty">No groups match your search yet.</div>';});
  }

  document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal();});
  function bindNavigation(){
    var search=document.querySelector('[data-community-focus-search]');
    if(search&&!search.dataset.bound){search.dataset.bound='1';search.addEventListener('click',function(){var input=root.querySelector('#bh-community-search');if(input){input.focus();input.scrollIntoView({behavior:'smooth',block:'center'});}});}
    var menu=document.querySelector('.bh-community-menu'),mobile=document.getElementById('bh-community-mobile-menu');
    if(menu&&mobile&&!menu.dataset.bound){menu.dataset.bound='1';menu.addEventListener('click',function(){var openState=menu.getAttribute('aria-expanded')==='true';menu.setAttribute('aria-expanded',String(!openState));mobile.hidden=openState;});}
  }
  bindNavigation();
  var tries=0;
  var observer=new MutationObserver(function(){bindNavigation();if(root.querySelector('.bh-main')&&tries<12){tries++;setTimeout(build,80);}});
  observer.observe(root,{childList:true,subtree:true});
  setTimeout(build,350);
})();
